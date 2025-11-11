<?php

namespace app\components;

use app\models\MailArchive;
use app\models\forms\MailReceptionForm;
use app\models\forms\MailReceptionRequestForm;
use Mpdf\Mpdf;
use RuntimeException;
use yii\base\Component;
use yii\helpers\FileHelper;

class InvoiceMailboxService extends Component
{
    /**
     * Alias donde se almacenan los archivos generados.
     */
    public $storageAlias = '@runtime/invoice-archives';

    /**
     * Verifica la conexión IMAP utilizando las credenciales proporcionadas.
     *
     * @throws RuntimeException
     */
    public function testConnection(MailReceptionForm $credentials): void
    {
        $this->ensureImapAvailable();

        $mailbox = $credentials->getImapMailboxString();
        $resource = @imap_open($mailbox, $credentials->getLoginIdentifier(), $credentials->password, OP_HALFOPEN);

        if ($resource === false) {
            $error = imap_last_error() ?: 'No fue posible conectarse al buzón.';
            throw new RuntimeException($error);
        }

        imap_close($resource);
    }

    /**
     * Procesa los correos del periodo solicitado y genera el archivo correspondiente.
     *
     * @return array con claves: archive (MailArchive), summary (array), warnings (array)
     *
     * @throws RuntimeException
     */
    public function processMailbox(MailReceptionForm $credentials, MailReceptionRequestForm $request): array
    {
        $this->ensureImapAvailable();
        $this->ensureStorageDirectory();

        $mailbox = $credentials->getImapMailboxString();
        $imap = @imap_open($mailbox, $credentials->getLoginIdentifier(), $credentials->password);

        if ($imap === false) {
            $error = imap_last_error() ?: 'No fue posible conectarse al buzón.';
            throw new RuntimeException($error);
        }

        try {
            $searchCriteria = $this->buildSearchCriteria($request->startDate, $request->endDate);
            $messageUids = imap_search($imap, $searchCriteria, SE_UID);

            $totalMessages = is_array($messageUids) ? count($messageUids) : 0;

            if (empty($messageUids)) {
                return [
                    'archive' => null,
                    'summary' => [
                        'totalMessages' => 0,
                        'totalInvoices' => 0,
                        'invoices' => [],
                    ],
                    'warnings' => ['No se encontraron correos en el periodo solicitado.'],
                ];
            }

            $invoiceEntries = [];
            $invoiceAttachments = [];
            $warnings = [];

            foreach ($messageUids as $uid) {
                $messageNumber = imap_msgno($imap, $uid);
                $header = imap_headerinfo($imap, $messageNumber);
                $subject = $header && property_exists($header, 'subject') ? imap_utf8($header->subject) : '(Sin asunto)';
                $date = $header && property_exists($header, 'date') ? $header->date : null;

                $attachments = $this->extractXmlAttachments($imap, $messageNumber);

                foreach ($attachments as $attachment) {
                    if (!$attachment['isInvoice']) {
                        continue;
                    }

                    $invoiceEntries[] = [
                        'subject' => $subject,
                        'date' => $date,
                        'filename' => $attachment['filename'],
                        'invoice' => $attachment['invoice'],
                    ];

                    $invoiceAttachments[] = [
                        'filename' => $attachment['filename'],
                        'content' => $attachment['content'],
                    ];
                }
            }

            if (empty($invoiceEntries)) {
                return [
                    'archive' => null,
                    'summary' => [
                        'totalMessages' => $totalMessages,
                        'totalInvoices' => 0,
                        'invoices' => [],
                    ],
                    'warnings' => ['No se encontraron facturas electrónicas 4.4 en el periodo indicado.'],
                ];
            }

            $archive = $this->createArchive($credentials, $request, $invoiceEntries, $invoiceAttachments, $totalMessages);

            return [
                'archive' => $archive,
                'summary' => [
                    'totalMessages' => $totalMessages,
                    'totalInvoices' => count($invoiceEntries),
                    'invoices' => $invoiceEntries,
                ],
                'warnings' => $warnings,
            ];
        } finally {
            imap_close($imap);
        }
    }

    /**
     * Construye el criterio de búsqueda IMAP para el rango de fechas.
     */
    protected function buildSearchCriteria(string $startDate, string $endDate): string
    {
        $start = date_create($startDate);
        $end = date_create($endDate . ' 23:59:59');

        if (!$start || !$end) {
            throw new RuntimeException('Las fechas proporcionadas no son válidas.');
        }

        $startFormatted = $start->format('d-M-Y');
        $endPlusOne = clone $end;
        $endPlusOne->modify('+1 day');
        $endFormatted = $endPlusOne->format('d-M-Y');

        return sprintf('SINCE "%s" BEFORE "%s"', $startFormatted, $endFormatted);
    }

    /**
     * Extrae los adjuntos XML con estructura de factura electrónica.
     *
     * @return array<int, array{filename: string, content: string, isInvoice: bool, invoice: array}>
     */
    protected function extractXmlAttachments($imap, int $messageNumber): array
    {
        $structure = imap_fetchstructure($imap, $messageNumber);
        $attachments = [];

        if (!isset($structure->parts) || !is_array($structure->parts)) {
            return $attachments;
        }

        $queue = [];
        foreach ($structure->parts as $index => $part) {
            $queue[] = [$part, (string) ($index + 1)];
        }

        while ($queue) {
            [$part, $partNumber] = array_shift($queue);

            $filename = null;
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $object) {
                    if (strtolower($object->attribute ?? '') === 'filename') {
                        $filename = $object->value ?? null;
                        break;
                    }
                }
            }
            if ($filename === null && isset($part->parameters)) {
                foreach ($part->parameters as $object) {
                    if (strtolower($object->attribute ?? '') === 'name') {
                        $filename = $object->value ?? null;
                        break;
                    }
                }
            }

            if ($filename && stripos($filename, '.xml') !== false) {
                $body = imap_fetchbody($imap, $messageNumber, $partNumber);
                $decoded = $this->decodePartBody($body, $part->encoding ?? 0);

                $invoiceInfo = $this->parseInvoiceXml($decoded);

                $attachments[] = [
                    'filename' => $filename,
                    'content' => $decoded,
                    'isInvoice' => $invoiceInfo['isInvoice'],
                    'invoice' => $invoiceInfo['data'],
                ];
            }

            if (isset($part->parts) && is_array($part->parts)) {
                foreach ($part->parts as $childIndex => $childPart) {
                    $queue[] = [$childPart, $partNumber . '.' . ($childIndex + 1)];
                }
            }
        }

        return $attachments;
    }

    /**
     * Decodifica el cuerpo de una parte IMAP según su codificación.
     */
    protected function decodePartBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case ENCBASE64:
                return base64_decode($body) ?: '';
            case ENCQUOTEDPRINTABLE:
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * Intenta parsear un XML de factura electrónica y devuelve la información relevante.
     *
     * @return array{isInvoice: bool, data: array|null}
     */
    protected function parseInvoiceXml(string $xmlContent): array
    {
        if (trim($xmlContent) === '') {
            return ['isInvoice' => false, 'data' => null];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false || !empty($errors)) {
            return ['isInvoice' => false, 'data' => null];
        }

        $rootName = $xml->getName();
        if (stripos($rootName, 'mensaje') !== false) {
            return ['isInvoice' => false, 'data' => null];
        }

        $isValidRoot = in_array($rootName, [
            'FacturaElectronica',
            'FacturaElectronicaCompra',
            'TiqueteElectronico',
            'NotaCreditoElectronica',
            'NotaDebitoElectronica',
        ], true);

        if (!$isValidRoot) {
            return ['isInvoice' => false, 'data' => null];
        }

        $resumen = $xml->ResumenFactura ?? null;

        $data = [
            'clave' => (string) ($xml->Clave ?? ''),
            'codigoActividad' => (string) ($xml->CodigoActividad ?? ''),
            'emisorNombre' => (string) ($xml->Emisor->Nombre ?? ''),
            'emisorIdentificacion' => (string) ($xml->Emisor->Identificacion->Numero ?? ''),
            'receptorNombre' => (string) ($xml->Receptor->Nombre ?? ''),
            'receptorIdentificacion' => (string) ($xml->Receptor->Identificacion->Numero ?? ''),
            'fechaEmision' => (string) ($xml->FechaEmision ?? ''),
            'subtotal' => $resumen ? (float) ($resumen->TotalVentaNeta ?? 0) : 0.0,
            'impuestos' => $resumen ? (float) ($resumen->TotalImpuesto ?? 0) : 0.0,
            'total' => $resumen ? (float) ($resumen->TotalComprobante ?? 0) : 0.0,
        ];

        return ['isInvoice' => true, 'data' => $data];
    }

    /**
     * Genera el archivo solicitado y crea el registro en base de datos.
     */
    protected function createArchive(
        MailReceptionForm $credentials,
        MailReceptionRequestForm $request,
        array $invoiceEntries,
        array $invoiceAttachments,
        int $totalMessages
    ): MailArchive {
        $storagePath = $this->ensureStorageDirectory();
        $timestamp = date('Ymd_His');
        $baseFileName = sprintf(
            'facturas_%s_%s_%s',
            preg_replace('/[^a-z0-9]+/i', '_', $credentials->email),
            $request->startDate,
            $request->endDate
        );

        $archive = new MailArchive();
        $archive->account_email = $credentials->email;
        $archive->period_start = $request->startDate;
        $archive->period_end = $request->endDate;
        $archive->file_type = $request->outputType;
        $archive->total_messages = $totalMessages;
        $archive->total_invoices = count($invoiceEntries);

        if ($request->outputType === MailArchive::TYPE_PDF) {
            $fileName = $baseFileName . '_' . $timestamp . '.pdf';
            $filePath = $storagePath . DIRECTORY_SEPARATOR . $fileName;
            $this->generatePdfSummary($filePath, $invoiceEntries, $credentials, $request);
        } else {
            $extension = $request->outputType === MailArchive::TYPE_ZIP ? 'zip' : 'rar';
            $fileName = $baseFileName . '_' . $timestamp . '.' . $extension;
            $filePath = $storagePath . DIRECTORY_SEPARATOR . $fileName;
            $this->generateCompressedFile($filePath, $invoiceAttachments, $request);
        }

        $relativePath = $this->convertToAliasPath($filePath);

        $archive->file_name = $fileName;
        $archive->file_path = $relativePath;
        $archive->metadata = json_encode([
            'generated_at' => date('c'),
            'compression' => $request->outputType,
            'storage_path' => $filePath,
        ]);

        if (!$archive->save()) {
            throw new RuntimeException('No fue posible guardar el registro del archivo generado.');
        }

        return $archive;
    }

    /**
     * Genera el archivo PDF con el resumen de facturas.
     */
    protected function generatePdfSummary(
        string $filePath,
        array $invoiceEntries,
        MailReceptionForm $credentials,
        MailReceptionRequestForm $request
    ): void {
        $mpdf = new Mpdf([
            'tempDir' => sys_get_temp_dir(),
        ]);

        ob_start();
        $view = \Yii::$app->view;
        $html = $view->render('@app/views/admin/_invoice_summary_pdf', [
            'entries' => $invoiceEntries,
            'credentials' => $credentials,
            'request' => $request,
            'generatedAt' => date('d/m/Y H:i:s'),
        ]);
        ob_end_clean();

        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
    }

    /**
     * Genera un archivo comprimido con los XML de facturas.
     */
    protected function generateCompressedFile(
        string $filePath,
        array $invoiceAttachments,
        MailReceptionRequestForm $request
    ): void {
        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el archivo comprimido.');
        }

        foreach ($invoiceAttachments as $attachment) {
            $zip->addFromString($attachment['filename'], $attachment['content']);
        }

        $zip->close();

        // Para archivos "RAR" informamos en los metadatos.
        if ($request->outputType === MailArchive::TYPE_RAR) {
            $metaPath = $filePath . '.note.txt';
            file_put_contents($metaPath, 'Este archivo fue generado en formato ZIP por compatibilidad.');
        }
    }

    /**
     * Asegura que la extensión IMAP esté disponible.
     */
    protected function ensureImapAvailable(): void
    {
        if (!function_exists('imap_open')) {
            throw new RuntimeException('La extensión IMAP de PHP no está disponible en el servidor.');
        }
    }

    /**
     * Garantiza que la carpeta de almacenamiento exista.
     */
    protected function ensureStorageDirectory(): string
    {
        $path = \Yii::getAlias($this->storageAlias);
        if (!is_dir($path)) {
            FileHelper::createDirectory($path, 0775, true);
        }

        return $path;
    }

    /**
     * Convierte una ruta absoluta a un alias almacenado.
     */
    protected function convertToAliasPath(string $absolutePath): string
    {
        $storage = \Yii::getAlias($this->storageAlias);
        if (strpos($absolutePath, $storage) === 0) {
            $relative = substr($absolutePath, strlen($storage));
            $relative = ltrim(str_replace('\\', '/', $relative), '/');

            return $this->storageAlias . '/' . $relative;
        }

        return $absolutePath;
    }
}

