<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $order->reference }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background-color: #ffffff;
            line-height: 1.5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 35px;
            background: #ffffff;
        }

        /* HEADER - Logo et entreprise */
        .header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 3px solid #ea580c;
        }

        .company-section {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            flex: 1;
        }

        .company-logo {
            width: 140px;
            height: auto;
            max-height: 140px;
            object-fit: contain;
        }

        .company-details {
            flex: 1;
        }

        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #ea580c;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .company-info {
            font-size: 10px;
            color: #4b5563;
            line-height: 1.6;
            margin-top: 8px;
        }

        .company-info div {
            margin-bottom: 3px;
        }

        .invoice-header {
            text-align: right;
            padding-top: 5px;
        }

        .invoice-title {
            font-size: 32px;
            font-weight: 800;
            color: #ea580c;
            margin-bottom: 8px;
            letter-spacing: 1.5px;
        }

        .invoice-number {
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .invoice-date {
            font-size: 11px;
            color: #6b7280;
        }

        /* Informations Client et Commande */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 30px;
        }

        .info-box {
            flex: 1;
            background: #fff7ed;
            padding: 18px;
            border-radius: 6px;
            border-left: 4px solid #ea580c;
        }

        .info-title {
            font-size: 12px;
            font-weight: 700;
            color: #ea580c;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-content {
            font-size: 11px;
            color: #1f2937;
            line-height: 1.8;
        }

        .info-content strong {
            color: #111827;
            font-weight: 600;
        }

        /* Tableau des articles */
        .items-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #ea580c;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 8px;
            border-bottom: 2px solid #fed7aa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            background: #ffffff;
        }

        table thead {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
        }

        table th {
            color: #ffffff;
            padding: 14px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        table th:first-child {
            padding-left: 18px;
        }

        table th:last-child {
            padding-right: 18px;
        }

        table tbody tr {
            border-bottom: 1px solid #fed7aa;
        }

        table tbody tr:last-child {
            border-bottom: none;
        }

        table tbody tr:nth-child(even) {
            background-color: #fff7ed;
        }

        table td {
            padding: 14px 12px;
            font-size: 11px;
            color: #1f2937;
        }

        table td:first-child {
            padding-left: 18px;
            font-weight: 500;
        }

        table td:last-child {
            padding-right: 18px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totaux */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 35px;
        }

        .totals-box {
            width: 340px;
            background: #fff7ed;
            border: 2px solid #ea580c;
            border-radius: 8px;
            padding: 18px 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 11px;
        }

        .total-row:not(:last-child) {
            border-bottom: 1px solid #fed7aa;
        }

        .total-row:last-child {
            margin-top: 8px;
            padding-top: 12px;
            border-top: 2px solid #ea580c;
            font-weight: 700;
            font-size: 13px;
            color: #ea580c;
        }

        .total-label {
            color: #4b5563;
        }

        .total-value {
            color: #1f2937;
            font-weight: 600;
        }

        .total-row:last-child .total-label {
            color: #ea580c;
            font-size: 13px;
        }

        .total-row:last-child .total-value {
            color: #ea580c;
            font-size: 15px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #fbbf24;
            color: #78350f;
        }

        .status-confirmed {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .status-delivered {
            background-color: #10b981;
            color: #ffffff;
        }

        .status-cancelled {
            background-color: #ef4444;
            color: #ffffff;
        }

        /* Footer */
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #fed7aa;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }

        .footer-message {
            font-style: italic;
            margin-bottom: 5px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header avec Logo et Informations -->
        <div class="header-top">
            <div class="company-section">
                @php
                    $logoPath = public_path('assets/logos/logo_business.png');
                    $logoBase64 = null;
                    if (file_exists($logoPath)) {
                        try {
                            $imageData = file_get_contents($logoPath);
                            if ($imageData !== false) {
                                $logoBase64 = 'data:image/png;base64,' . base64_encode($imageData);
                            }
                        } catch (\Exception $e) {
                            $logoBase64 = null;
                        }
                    }
                @endphp
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="BS INTERNATIONAL BUSINESS Logo" class="company-logo" />
                @endif
                <div class="company-details">
                    <div class="company-name">BS INTERNATIONAL BUSINESS</div>
                    <div class="company-info">
                        @if($company['address'])
                            <div>{{ $company['address'] }}</div>
                        @endif
                        @if($company['phone'])
                            <div>Tél: {{ $company['phone'] }}</div>
                        @endif
                        @if($company['email'])
                            <div>Email: {{ $company['email'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="invoice-header">
                <div class="invoice-title">FACTURE</div>
                <div class="invoice-number">N° {{ $order->reference }}</div>
                <div class="invoice-date">Date: {{ $order->created_at->format('d/m/Y') }}</div>
                <div style="margin-top: 12px;">
                    <span class="status-badge status-{{ $order->status }}">
                        {{ str_replace('_', ' ', ucfirst($order->status)) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Informations Client et Commande -->
        <div class="info-section">
            <div class="info-box">
                <div class="info-title">Client</div>
                <div class="info-content">
                    <div><strong>{{ $order->client->first_name }} {{ $order->client->last_name }}</strong></div>
                    @if($order->client->phone)
                        <div>Tél: {{ $order->client->phone }}</div>
                    @endif
                    @if($order->client->email)
                        <div>Email: {{ $order->client->email }}</div>
                    @endif
                    <div>{{ $order->client->city }}, {{ $order->client->country }}</div>
                </div>
            </div>
            <div class="info-box">
                <div class="info-title">Informations de la commande</div>
                <div class="info-content">
                    @if($order->wave)
                        <div><strong>Vague:</strong> {{ $order->wave->name }}</div>
                    @endif
                    @if($order->convoy)
                        <div><strong>Convoi:</strong> {{ $order->convoy->name }}</div>
                    @endif
                    <div><strong>Devise:</strong> {{ $order->currency }}</div>
                </div>
            </div>
        </div>

        <!-- Articles commandés -->
        <div class="items-section">
            <div class="section-title">Articles commandés</div>
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="text-center">Quantité</th>
                        <th class="text-right">Prix unitaire</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product->name ?? 'Produit' }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2, ',', ' ') }} {{ $order->currency }}</td>
                        <td class="text-right"><strong>{{ number_format($item->total_price, 2, ',', ' ') }} {{ $order->currency }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totaux -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row">
                    <span class="total-label">Total HT:</span>
                    <span class="total-value">{{ number_format($order->total_amount, 2, ',', ' ') }} {{ $order->currency }}</span>
                </div>
                @if($order->total_paid > 0)
                <div class="total-row">
                    <span class="total-label">Montant payé:</span>
                    <span class="total-value">{{ number_format($order->total_paid, 2, ',', ' ') }} {{ $order->currency }}</span>
                </div>
                <div class="total-row">
                    <span class="total-label">Reste à payer:</span>
                    <span class="total-value">{{ number_format($order->total_amount - $order->total_paid, 2, ',', ' ') }} {{ $order->currency }}</span>
                </div>
                @endif
                <div class="total-row">
                    <span class="total-label">Total TTC:</span>
                    <span class="total-value">{{ number_format($order->total_amount, 2, ',', ' ') }} {{ $order->currency }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-message">Merci de votre confiance !</div>
            <div>Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>
</body>
</html>
