<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\Exporter;

use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sonata\Exporter\Writer\TypedWriterInterface;

class XlsxWriter implements TypedWriterInterface
{
    private ?Spreadsheet $spreadsheet = null;
    private int $rowNumber = 1;
    private string $urlSuffix = '(URL)';

    /**
     * {@inheritDoc}
     */
    public function getDefaultMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * {@inheritDoc}
     */
    public function getFormat(): string
    {
        return 'xlsx';
    }

    /**
     * {@inheritDoc}
     */
    public function open(): void
    {
        $this->spreadsheet = new Spreadsheet();
        $this->rowNumber = 1;

        Cell::setValueBinder(new AdvancedValueBinder());
    }

    /**
     * {@inheritDoc}
     */
    public function write(array $data): void
    {
        if (null === $this->spreadsheet) {
            throw new \LogicException('Таблица не инициализирована (не вызван open())');
        }
        $worksheet = $this->spreadsheet->getActiveSheet();
        $fontSize = $worksheet->getStyle([1, 1])->getFont()->getSize();
        if (1 === $this->rowNumber) {
            $columnNumber = 1;
            foreach ($data as $key => $value) {
                if ($this->isUrlKeyExists($key, $data)) {
                    continue;
                }
                $cell = $worksheet->getCell([$columnNumber, $this->rowNumber]);
                $cell->setValue($key);
                $worksheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
                ++$columnNumber;
            }
            ++$this->rowNumber;
        }

        $columnNumber = 1;
        $maxCaretsCount = 0;
        foreach ($data as $key => $value) {
            if ($this->isUrlKeyExists($key, $data)) {
                continue;
            }
            $url = array_key_exists("$key{$this->urlSuffix}", $data) ? $data["$key{$this->urlSuffix}"] : null;

            $caretsCount = mb_substr_count((string) $value, "\n");
            $cell = $worksheet->getCell([$columnNumber, $this->rowNumber]);
            $cell->setValue($value);
            if ($url) {
                $cell->getHyperlink()->setUrl($url);
            }
            $style = $cell->getStyle();
            $alignment = $style->getAlignment();
            $alignment->setVertical(Alignment::VERTICAL_TOP);
            $alignment->setWrapText(true);
            ++$columnNumber;
            $maxCaretsCount = max($maxCaretsCount, $caretsCount);
        }
        $worksheet->getRowDimension($this->rowNumber)->setRowHeight($fontSize * 1.4 * ($maxCaretsCount + 1));

        ++$this->rowNumber;
    }

    public function close(): void
    {
        if (null === $this->spreadsheet) {
            throw new \LogicException('Таблица не инициализирована (не вызван open())');
        }
        $writer = new Xlsx($this->spreadsheet);

        $writer->save('php://output');
    }

    private function isUrlKeyExists(int|string $key, array $data): bool
    {
        $key = (string) $key;

        return str_ends_with($key, $this->urlSuffix) && array_key_exists(substr($key, 0, \strlen($key) - \strlen($this->urlSuffix)), $data);
    }
}
