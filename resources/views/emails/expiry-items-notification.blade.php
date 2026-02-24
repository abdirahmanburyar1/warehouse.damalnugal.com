<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expiry Items Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-bottom: 3px solid #e9ecef; }
        .content { padding: 20px; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #dee2e6; }
        .section-expired .section-title { color: #e74c3c; }
        .section-6months .section-title { color: #e67e22; }
        .section-1year .section-title { color: #3498db; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 14px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Expiry Items Notification</h1>
            <p>Warehouse Management System</p>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>This is an automated notification for inventory items by expiry policy:</p>
            <p><strong>Summary:</strong> {{ $summaryText }}</p>

            @if($expiredItems->isNotEmpty())
            <div class="section section-expired">
                <div class="section-title">Already expired</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Warehouse</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expiredItems as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }}</td>
                            <td>{{ $item->batch_number ?? '—' }}</td>
                            <td>{{ $item->warehouse->name ?? 'N/A' }}</td>
                            <td>{{ $item->location ?? '—' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->expiry_date)->format('Y-m-d') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($expiring6MonthsItems->isNotEmpty())
            <div class="section section-6months">
                <div class="section-title">Expiring within 6 months</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Warehouse</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expiring6MonthsItems as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }}</td>
                            <td>{{ $item->batch_number ?? '—' }}</td>
                            <td>{{ $item->warehouse->name ?? 'N/A' }}</td>
                            <td>{{ $item->location ?? '—' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->expiry_date)->format('Y-m-d') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($expiring1YearItems->isNotEmpty())
            <div class="section section-1year">
                <div class="section-title">Expiring within 1 year (beyond 6 months)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Warehouse</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expiring1YearItems as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }}</td>
                            <td>{{ $item->batch_number ?? '—' }}</td>
                            <td>{{ $item->warehouse->name ?? 'N/A' }}</td>
                            <td>{{ $item->location ?? '—' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->expiry_date)->format('Y-m-d') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <p>Please review and take appropriate action (disposal, transfer, or use) for these items.</p>
            <p>Thank you,<br>Warehouse Management System</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
