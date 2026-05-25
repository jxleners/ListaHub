<?php

class FPDF
{
    public $page = 0;
    public $pages = [];
    public $orientation = 'P';
    public $unit = 'mm';
    public $size = 'A4';
    public $w = 210;
    public $h = 297;
    public $leftMargin = 10;
    public $topMargin = 10;
    public $rightMargin = 10;
    public $bottomMargin = 10;
    public $x = 10;
    public $y = 10;
    public $fontFamily = 'Helvetica';
    public $fontStyle = '';
    public $fontSize = 10;
    public $textColor = [0, 0, 0];
    public $fillColor = [255, 255, 255];
    public $drawColor = [0, 0, 0];
    public $autoPageBreak = true;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        $this->orientation = strtoupper($orientation);
        $this->unit = $unit;
        $this->size = $size;

        if ($this->orientation === 'L') {
            $this->w = 297;
            $this->h = 210;
        } else {
            $this->w = 210;
            $this->h = 297;
        }

        $this->x = $this->leftMargin;
        $this->y = $this->topMargin;
        $this->AddPage();
    }

    protected function mm2pt($value)
    {
        return $value * 72 / 25.4;
    }

    public function AddPage($orientation = '')
    {
        $this->page++;
        $this->pages[$this->page] = ['content' => ''];
        $this->x = $this->leftMargin;
        $this->y = $this->topMargin;
        $this->SetFont($this->fontFamily, $this->fontStyle, $this->fontSize);
        $this->SetTextColor($this->textColor[0], $this->textColor[1], $this->textColor[2]);
        $this->SetDrawColor($this->drawColor[0], $this->drawColor[1], $this->drawColor[2]);
        $this->SetFillColor($this->fillColor[0], $this->fillColor[1], $this->fillColor[2]);

        return $this->page;
    }

    public function SetAutoPageBreak($auto, $margin = 0)
    {
        $this->autoPageBreak = (bool) $auto;
        if ($margin > 0) {
            $this->bottomMargin = $margin;
        }
    }

    public function SetFont($family, $style = '', $size = 0)
    {
        $this->fontFamily = $family;
        $this->fontStyle = strtoupper($style);
        if ($size > 0) {
            $this->fontSize = $size;
        }
    }

    public function SetTextColor($r, $g = null, $b = null)
    {
        if ($g === null && $b === null) {
            $this->textColor = [$r, $r, $r];
        } else {
            $this->textColor = [$r, $g, $b];
        }
    }

    public function SetDrawColor($r, $g = null, $b = null)
    {
        if ($g === null && $b === null) {
            $this->drawColor = [$r, $r, $r];
        } else {
            $this->drawColor = [$r, $g, $b];
        }
    }

    public function SetFillColor($r, $g = null, $b = null)
    {
        if ($g === null && $b === null) {
            $this->fillColor = [$r, $r, $r];
        } else {
            $this->fillColor = [$r, $g, $b];
        }
    }

    public function SetXY($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function SetLeftMargin($margin)
    {
        $this->leftMargin = $margin;
    }

    public function SetTopMargin($margin)
    {
        $this->topMargin = $margin;
    }

    public function SetRightMargin($margin)
    {
        $this->rightMargin = $margin;
    }

    public function Ln($h = null)
    {
        if ($h === null) {
            $h = $this->fontSize * 1.2;
        }

        $this->checkPageBreak($h);
        $this->x = $this->leftMargin;
        $this->y += $h;
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        if ($h <= 0) {
            $h = $this->fontSize * 1.2;
        }

        $this->checkPageBreak($h);

        if ($fill) {
            $this->Rect($this->x, $this->y, $w, $h, 'F');
        }

        $x = $this->mm2pt($this->x);
        $y = $this->mm2pt($this->pdfY($this->y, $h));
        $text = $this->escapeText($txt);
        $color = $this->colorCmd($this->textColor);

        $this->pages[$this->page]['content'] .= sprintf("BT /F1 %.2F Tf %.2F %.2F Td %s (%s) Tj ET\n", $this->fontSize, $x + 1.5, $y + 2.5, $color, $text);

        if ($ln === 1) {
            $this->x = $this->leftMargin;
            $this->y += $h;
        } else {
            $this->x += $w;
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
    {
        $lines = $this->wrapText($txt, $w);
        foreach ($lines as $line) {
            $this->Cell($w, $h, $line, $border, 1, $align, $fill);
        }
    }

    public function Rect($x, $y, $w, $h, $style = '')
    {
        $xPt = $this->mm2pt($x);
        $yPt = $this->mm2pt($this->pdfY($y, $h));
        $wPt = $this->mm2pt($w);
        $hPt = $this->mm2pt($h);

        if (strpos($style, 'F') !== false) {
            $fillColor = $this->colorCmd($this->fillColor);
            $this->pages[$this->page]['content'] .= sprintf("%.2F %.2F %.2F %.2F re %s f\n", $xPt, $yPt, $wPt, $hPt, $fillColor);
        }

        if (strpos($style, 'D') !== false || $style === '') {
            $drawColor = $this->colorCmd($this->drawColor);
            $this->pages[$this->page]['content'] .= sprintf("%.2F %.2F %.2F %.2F re %s S\n", $xPt, $yPt, $wPt, $hPt, $drawColor);
        }
    }

    public function checkPageBreak($h)
    {
        if ($this->autoPageBreak && $this->y + $h > $this->h - $this->bottomMargin) {
            $this->AddPage();
        }
    }

    protected function pdfY($y, $h = 0)
    {
        return max(0, $this->h - $y - $h);
    }

    public function Output($dest = 'I', $name = '')
    {
        $buffer = $this->buildPdf();

        if ($dest === 'F') {
            file_put_contents($name, $buffer);
            return $buffer;
        }

        if ($dest === 'S') {
            return $buffer;
        }

        $filename = $name ?: 'inventory-export.pdf';
        $disposition = $dest === 'D' ? 'attachment' : 'inline';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($buffer));
        echo $buffer;
        return '';
    }

    protected function colorCmd(array $color)
    {
        $r = max(0, min(255, (int) round($color[0])));
        $g = max(0, min(255, (int) round($color[1])));
        $b = max(0, min(255, (int) round($color[2])));

        return sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }

    protected function escapeText($text)
    {
        $text = (string) $text;
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'windows-1252//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return $text;
    }

    protected function wrapText($text, $width)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [''];
        }

        $widthChars = max(1, (int) round($width / 1.8));
        if (function_exists('mb_strlen')) {
            if (mb_strlen($text) <= $widthChars) {
                return [$text];
            }

            return [mb_substr($text, 0, $widthChars - 1) . '…'];
        }

        return [substr($text, 0, $widthChars - 1) . '…'];
    }

    protected function buildPdf()
    {
        $objects = [];
        $pageObjects = [];
        $contentObjects = [];
        $objectNumber = 4;

        foreach ($this->pages as $pageData) {
            $contentObj = $objectNumber++;
            $pageObj = $objectNumber++;
            $contentObjects[] = $contentObj;
            $pageObjects[] = $pageObj;

            $stream = $pageData['content'];
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$pageObj] = "<< /Type /Page /Parent 1 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentObj 0 R >>";
        }

        $kids = implode(' ', array_map(static fn($id) => "$id 0 R", $pageObjects));
        $objects[1] = "<< /Type /Pages /Kids [$kids] /Count " . count($this->pages) . " >>";
        $objects[2] = "<< /Type /Catalog /Pages 1 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $objectNumber => $objectBody) {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $objectNumber . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 2 0 R >>\nstartxref\n$xrefOffset\n%%EOF";

        return $pdf;
    }
}
