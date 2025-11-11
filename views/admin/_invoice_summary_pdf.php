<?php
/**
 * @var array $entries
 * @var \app\models\forms\MailReceptionForm $credentials
 * @var \app\models\forms\MailReceptionRequestForm $request
 * @var string $generatedAt
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11pt;
            color: #0f1d41;
        }
        h1 {
            text-align: center;
            font-size: 18pt;
            margin-bottom: 5mm;
        }
        h2 {
            font-size: 13pt;
            margin-bottom: 3mm;
            color: #1b2b5e;
        }
        .metadata {
            font-size: 10pt;
            margin-bottom: 10mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }
        th, td {
            border: 1px solid #1b2b5e;
            padding: 4px 6px;
            font-size: 9pt;
        }
        th {
            background-color: #e9edf7;
            font-weight: bold;
        }
        .footer {
            margin-top: 10mm;
            font-size: 9pt;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Resumen de Facturas Electrónicas</h1>

    <div class="metadata">
        <p><strong>Cuenta:</strong> <?= htmlspecialchars($credentials->email, ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Periodo:</strong> <?= htmlspecialchars($request->startDate, ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars($request->endDate, ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Generado:</strong> <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha correo</th>
                <th>Asunto</th>
                <th>Clave</th>
                <th>Emisor</th>
                <th>Subtotal</th>
                <th>IVA</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $index => $entry): ?>
            <?php $invoice = $entry['invoice']; ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($entry['date'] ?: 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($entry['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($invoice['clave'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($invoice['emisorNombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format($invoice['subtotal'] ?? 0, 2) ?></td>
                <td><?= number_format($invoice['impuestos'] ?? 0, 2) ?></td>
                <td><?= number_format($invoice['total'] ?? 0, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Reporte generado automáticamente por Herramientas Facto Facturación Electrónica.
    </div>
</body>
</html>

