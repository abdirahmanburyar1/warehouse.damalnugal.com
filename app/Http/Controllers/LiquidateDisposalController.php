<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Disposal;
use App\Models\Liquidate;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\DisposalResource;
use App\Http\Resources\LiquidateResource;
use App\Notifications\LiquidationActionRequired;
use App\Notifications\DisposalActionRequired;

class LiquidateDisposalController extends Controller
{
    /**
     * Main index method for the tabbed interface
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('wastage-view')) {
            abort(403, 'You do not have permission to view wastages.');
        }

        // Get liquidations
        $liquidates = $this->getLiquidatesQuery($request);
        $liquidates = $liquidates->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        // Get disposals
        $disposals = $this->getDisposalsQuery($request);
        $disposals = $disposals->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        // Get statistics
        $stats = [
            'liquidate' => Liquidate::count(),
            'disposal' => Disposal::count(),
            'all' => Liquidate::count() + Disposal::count(),
            'pending' => Liquidate::where('status', 'pending')->count() + Disposal::where('status', 'pending')->count(),
            'reviewed' => Liquidate::where('status', 'reviewed')->count() + Disposal::where('status', 'reviewed')->count(),
            'approved' => Liquidate::where('status', 'approved')->count() + Disposal::where('status', 'approved')->count(),
            'rejected' => Liquidate::where('status', 'rejected')->count() + Disposal::where('status', 'rejected')->count(),
        ];

        return inertia('LiquidateDisposal/Index', [
            'liquidates' => LiquidateResource::collection($liquidates),
            'disposals' => DisposalResource::collection($disposals),
            'filters' => $request->only('search', 'per_page', 'page', 'source', 'date_from', 'date_to', 'status'),
            'stats' => $stats,
        ]);
    }

    /**
     * Get liquidations query with filters
     */
    private function getLiquidatesQuery(Request $request)
    {
        $liquidates = Liquidate::query()->with([
            'liquidatedBy:id,name',
            'approvedBy:id,name',
            'reviewedBy:id,name',
            'rejectedBy:id,name',
            'backOrder'
        ])->latest('liquidate_id');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $liquidates->where(function ($q) use ($search) {
                $q->where('liquidate_id', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Source filter
        if ($request->has('source') && $request->source) {
            $liquidates->where('source', $request->source);
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $liquidates->whereDate('liquidated_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $liquidates->whereDate('liquidated_at', '<=', $request->date_to);
        }

        // Status filter
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $liquidates->where('status', $request->status);
        }

        return $liquidates;
    }

    /**
     * Get disposals query with filters
     */
    private function getDisposalsQuery(Request $request)
    {
        $disposals = Disposal::query()->with([
            'items.product',
            'disposedBy',
            'approvedBy',
            'reviewedBy',
            'rejectedBy',
            'backOrder'
        ])->latest('disposal_id');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $disposals->where(function ($q) use ($search) {
                $q->where('disposal_id', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Source filter
        if ($request->has('source') && $request->source) {
            $disposals->where('source', $request->source);
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $disposals->whereDate('disposed_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $disposals->whereDate('disposed_at', '<=', $request->date_to);
        }

        // Status filter
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $disposals->where('status', $request->status);
        }

        return $disposals;
    }

    /**
     * Show individual liquidation details
     */
    public function showLiquidate($id)
    {
        if (!auth()->user()->hasPermission('wastage-view') && !auth()->user()->hasPermission('liquidation-view')) {
            abort(403, 'You do not have permission to view this liquidation.');
        }

        $liquidate = Liquidate::with([
            'items.product',
            'liquidatedBy',
            'approvedBy',
            'reviewedBy',
            'rejectedBy',
            'backOrder'
        ])->findOrFail($id);

        return inertia('LiquidateDisposal/Liquidate/Show', [
            'liquidate' => $liquidate,
        ]);
    }

    /**
     * Show individual disposal details
     */
    public function showDisposal($id)
    {
        if (!auth()->user()->hasPermission('wastage-view') && !auth()->user()->hasPermission('disposal-view')) {
            abort(403, 'You do not have permission to view this disposal.');
        }

        $disposal = Disposal::with([
            'items.product',
            'disposedBy',
            'approvedBy',
            'reviewedBy',
            'rejectedBy',
            'backOrder'
        ])->findOrFail($id);

        return inertia('LiquidateDisposal/Disposal/Show', [
            'disposal' => $disposal,
        ]);
    }

    /**
     * Review a liquidation
     */
    public function reviewLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-review')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to review liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            $liquidate->status = 'reviewed';
            $liquidate->reviewed_at = now();
            $liquidate->reviewed_by = Auth::id();
            $liquidate->save();

            // Notify users with approve/reject permission (next action)
            $approvers = User::withPermission('liquidation-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('liquidation-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new LiquidationActionRequired($liquidate, LiquidationActionRequired::ACTION_READY_FOR_APPROVAL));
            }

            return response()->json([
                'message' => 'Liquidation reviewed successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error reviewing liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a liquidation
     */
    public function approveLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-approve')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to approve liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            $liquidate->status = 'approved';
            $liquidate->approved_at = now();
            $liquidate->approved_by = Auth::id();
            $liquidate->rejected_at = null;
            $liquidate->rejection_reason = null;
            $liquidate->rejected_by = null;
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation approved successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error approving liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a liquidation
     */
    public function rejectLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-reject')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to reject liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            $liquidate->status = 'rejected';
            $liquidate->rejected_at = now();
            $liquidate->rejected_by = Auth::id();
            $liquidate->rejection_reason = $request->reason;
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation rejected successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rejecting liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback an approved liquidation to pending status
     */
    public function rollbackLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-edit')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to rollback liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            $liquidate->status = 'pending';
            $liquidate->approved_by = null;
            $liquidate->approved_at = null;
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation rolled back successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rolling back liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Review a disposal
     */
    public function reviewDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-review')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to review disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            if ($disposal->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending disposals can be reviewed'
                ], 422);
            }

            $disposal->status = 'reviewed';
            $disposal->reviewed_at = now();
            $disposal->reviewed_by = Auth::id();
            $disposal->save();

            // Notify users with approve/reject permission (next action)
            $approvers = User::withPermission('disposal-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('disposal-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_READY_FOR_APPROVAL));
            }

            return response()->json([
                'message' => 'Disposal reviewed successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error reviewing disposal: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a disposal
     */
    public function approveDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-approve')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to approve disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            if (!in_array($disposal->status, ['reviewed', 'rejected'])) {
                return response()->json([
                    'message' => 'Only reviewed or rejected disposals can be approved'
                ], 422);
            }

            $disposal->status = 'approved';
            $disposal->approved_at = now();
            $disposal->approved_by = Auth::id();
            $disposal->rejected_at = null;
            $disposal->rejection_reason = null;
            $disposal->rejected_by = null;
            $disposal->save();

            return response()->json([
                'message' => 'Disposal approved successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error approving disposal: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a disposal
     */
    public function rejectDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-reject')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to reject disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            if ($disposal->status !== 'reviewed') {
                return response()->json([
                    'message' => 'Only reviewed disposals can be rejected'
                ], 422);
            }

            $disposal->status = 'rejected';
            $disposal->rejected_at = now();
            $disposal->rejected_by = Auth::id();
            $disposal->rejection_reason = $request->reason;
            $disposal->save();

            return response()->json([
                'message' => 'Disposal rejected successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rejecting disposal: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback an approved disposal to pending status
     */
    public function rollbackDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-edit')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to rollback disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            if ($disposal->status !== 'approved') {
                return response()->json([
                    'message' => 'Only approved disposals can be rolled back'
                ], 422);
            }

            $disposal->status = 'pending';
            $disposal->approved_by = null;
            $disposal->approved_at = null;
            $disposal->save();

            return response()->json([
                'message' => 'Disposal rolled back successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rolling back disposal: ' . $th->getMessage()
            ], 500);
        }
    }
}
