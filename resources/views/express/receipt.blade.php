<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $parcel->reference }}</title>
    <style>
        @page {
            margin: 10mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            line-height: 1.4;
        }

        .container {
            width: 100%;
            padding: 8mm;
        }

        /* Header */
        .header {
            width: 100%;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .header-left {
            width: 32%;
            padding-top: 0;
        }

        .header-right {
            width: 68%;
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            gap: 12px;
        }

        .client-info {
            font-size: 10px;
        }

        .client-field {
            margin-bottom: 10px;
        }

        .client-label {
            font-weight: 600;
            margin-right: 6px;
        }

        .client-value {
            border-bottom: 1px dotted #333;
            padding-bottom: 2px;
            min-width: 160px;
            display: inline-block;
        }

        .logo-wrapper {
            flex-shrink: 0;
        }

        .company-logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-bottom: 5px;
        }

        .company-info-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
            max-width: 220px;
        }

        .company-title {
            font-size: 15px;
            font-weight: 700;
            color: #00459b;
            margin-bottom: 6px;
            text-transform: uppercase;
            width: 100%;
        }

        .company-details {
            font-size: 8px;
            color: #333;
            line-height: 1.5;
            width: 100%;
            text-align: left;
        }

        .company-details div {
            margin-bottom: 2px;
        }

        .website {
            color: #00459b;
            text-decoration: underline;
        }

        .doc-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            min-width: 140px;
            margin-top: 0;
        }

        .doc-field {
            font-size: 9px;
            width: 100%;
            text-align: right;
        }

        .doc-label {
            font-weight: 600;
            margin-right: 6px;
        }

        .doc-value {
            border-bottom: 1px dotted #333;
            padding-bottom: 2px;
            min-width: 100px;
            display: inline-block;
        }

        /* Informations du colis */
        .info-section {
            margin-bottom: 14px;
        }

        .info-field {
            margin-bottom: 7px;
            font-size: 10px;
        }

        .info-label {
            font-weight: 600;
            margin-right: 6px;
            min-width: 85px;
            display: inline-block;
        }

        .info-value {
            border-bottom: 1px dotted #333;
            padding-bottom: 2px;
            min-width: 200px;
            display: inline-block;
        }

        /* Tableau */
        .table-wrapper {
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        table thead {
            background-color: #00459b;
        }

        table th {
            color: #fff;
            padding: 9px 6px;
            text-align: center;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #003a7a;
        }

        table th:first-child {
            text-align: left;
            padding-left: 10px;
        }

        table tbody td {
            padding: 7px 6px;
            border: 1px solid #ddd;
            font-size: 9px;
            text-align: center;
            vertical-align: middle;
        }

        table tbody td:first-child {
            text-align: left;
            padding-left: 10px;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .checkmark {
            color: #27ae60;
            font-weight: bold;
            font-size: 13px;
        }

        /* Footer */
        .footer {
            width: 100%;
            margin-top: 22px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .footer-left {
            width: 48%;
        }

        .footer-right {
            width: 52%;
            text-align: right;
        }

        .payment-section {
            margin-bottom: 14px;
            font-size: 10px;
        }

        .payment-option {
            display: inline-block;
            margin-right: 18px;
        }

        .checkbox {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 2px solid #000;
            margin-right: 6px;
            vertical-align: middle;
            position: relative;
        }

        .checkbox.checked {
            background-color: #000;
        }

        .checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: -1px;
            left: 2px;
            color: white;
            font-size: 11px;
            font-weight: bold;
        }

        .signature-area {
            margin-top: 28px;
            padding-top: 5px;
            border-top: 1px dotted #333;
            width: 190px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }

        .totals {
            font-size: 10px;
        }

        .total-line {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .total-line:last-child {
            border-bottom: 2px solid #000;
            font-weight: 700;
            margin-top: 4px;
            padding-top: 7px;
        }

        .total-label {
            font-weight: 600;
        }

        .total-value {
            font-weight: 600;
            min-width: 130px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="client-info">
                        <div class="client-field">
                            <span class="client-label">Client:</span>
                            <span class="client-value">{{ $parcel->client->first_name }} {{ $parcel->client->last_name }}</span>
                        </div>
                        <div class="client-field">
                            <span class="client-label">Tél:</span>
                            <span class="client-value">{{ $parcel->client->phone ?? '' }}</span>
                        </div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="logo-wrapper">
                        @php
                            $logoPath = public_path('assets/logos/logo_express.jpg');
                            $logoBase64 = null;
                            if (file_exists($logoPath)) {
                                try {
                                    $imageData = file_get_contents($logoPath);
                                    if ($imageData !== false) {
                                        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode($imageData);
                                    }
                                } catch (\Exception $e) {
                                    $logoBase64 = null;
                                }
                            }
                        @endphp
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" alt="BS INTERNATIONAL EXPRESS Logo" class="company-logo" />
                        @endif
                    </div>
                    <div class="company-info-wrapper">
                        <div class="company-title">ADRESSE MAROC</div>
                        <div class="company-details">
                            <div>{{ $company['address'] }}</div>
                            <div class="website">{{ $company['website'] }}</div>
                            <div>Maroc: {{ $company['phone_maroc'] }}</div>
                            <div>Burkina Faso: {{ $company['phone_burkina'] }}</div>
                        </div>
                    </div>
                    <div class="doc-info">
                        <div class="doc-field">
                            <span class="doc-label">N° Document:</span>
                            <span class="doc-value">{{ $parcel->reference }}</span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-label">Date:</span>
                            <span class="doc-value">{{ $parcel->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations du colis -->
        <div class="info-section">
            <div class="info-field">
                <span class="info-label">Provenance:</span>
                <span class="info-value">{{ $parcel->trip->from_city }}, {{ $parcel->trip->from_country }}</span>
            </div>
            <div class="info-field">
                <span class="info-label">Destination:</span>
                <span class="info-value">{{ $parcel->trip->to_city }}, {{ $parcel->trip->to_country }}</span>
            </div>
            @if($parcel->receiverClient)
            <div class="info-field">
                <span class="info-label">Destinateur:</span>
                <span class="info-value">{{ $parcel->receiverClient->first_name }} {{ $parcel->receiverClient->last_name }}</span>
            </div>
            <div class="info-field">
                <span class="info-label">Tél:</span>
                <span class="info-value">{{ $parcel->receiverClient->phone ?? '' }}</span>
            </div>
            @endif
        </div>

        <!-- Tableau -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Quantité</th>
                        <th>Désignation</th>
                        <th>Poids</th>
                        <th>Prix U</th>
                        <th>Prix TOTAL</th>
                        <th>Valeur</th>
                        <th>A Expédier</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left">1</td>
                        <td class="text-left">{{ $parcel->description ?? 'Colis' }}</td>
                        <td>{{ number_format($parcel->weight_kg, 3, ',', ' ') }} kg</td>
                        <td class="text-right">{{ number_format($parcel->price_mad, 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($parcel->price_mad, 2, ',', ' ') }} Dhs</td>
                        <td class="text-right">{{ number_format($parcel->price_mad, 2, ',', ' ') }} Dhs</td>
                        <td><span class="checkmark">✓</span></td>
                    </tr>
                    @for($i = 0; $i < 8; $i++)
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="payment-section">
                        <span class="payment-option">
                            <span class="checkbox {{ $parcel->total_paid >= $parcel->price_mad ? 'checked' : '' }}"></span>
                            Payé
                        </span>
                        <span class="payment-option">
                            <span class="checkbox {{ $parcel->total_paid < $parcel->price_mad ? 'checked' : '' }}"></span>
                            Non Payé
                        </span>
                    </div>
                    <div class="signature-area">Signature</div>
                </div>
                <div class="footer-right">
                    <div class="totals">
                        <div class="total-line">
                            <span class="total-label">Total en Dhs</span>
                            <span class="total-value">{{ number_format($parcel->price_mad, 2, ',', ' ') }} Dhs</span>
                        </div>
                        <div class="total-line">
                            <span class="total-label">Total en Fcfa</span>
                            <span class="total-value">{{ number_format($parcel->price_cfa, 2, ',', ' ') }} Fcfa</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
