<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Support\SidangDocumentTemplate;
use App\Support\SidangOfficeTemplate;
use App\Support\SidangSettings;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

final class SidangWordWriter
{
    public function write(ThesisDefense $defense, ?string $form): void
    {
        $template = SidangDocumentTemplate::forProgram($defense->project->programStudi);
        abort_if($template === null, 422, 'Template dokumen prodi belum tersedia. Hubungi Admin prodi.');
        SidangOfficeTemplate::stream($template['word'], function (ZipArchive $zip) use ($defense, $form): void {
            $entry = 'word/document.xml';
            $xml = SidangOfficeTemplate::readXml($zip, $entry);
            $word = $xml->documentElement->namespaceURI;
            $query = new DOMXPath($xml);
            $query->registerNamespace('w', $word);
            $body = $query->query('/w:document/w:body')->item(0);
            $active = null;
            foreach (iterator_to_array($body->childNodes) as $node) {
                if ($node instanceof DOMComment && str_starts_with($node->data, 'sita:')) {
                    $active = substr($node->data, 5);
                    $node->parentNode->removeChild($node);
                } elseif ($node instanceof DOMElement && $node->localName !== 'sectPr' && $form !== null && $active !== $form) {
                    $node->parentNode->removeChild($node);
                }
            }
            if ($form !== null) {
                $first = $query->query('./w:p[1]', $body)->item(0);
                if ($first !== null) {
                    foreach ($query->query('./w:pPr/w:pageBreakBefore | .//w:br[@w:type="page"]', $first) as $break) {
                        $break->parentNode->removeChild($break);
                    }
                }
                $last = $query->query('./w:p[last()]', $body)->item(0);
                if ($last !== null) {
                    foreach ($query->query('./w:r/w:br[@w:type="page"]', $last) as $break) {
                        $break->parentNode->removeChild($break);
                    }
                }
            }
            $fields = app(SidangPaperPresenter::class)->fields($defense);
            $settings = app(SidangScoreService::class)->summary($defense)['settings'] ?? SidangSettings::forProgram($defense->project->programStudi);
            $this->fillRubrics($xml, $settings);
            $this->fillFields($xml, $fields);
            SidangOfficeTemplate::saveXml($zip, $entry, $xml);

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);
                if (is_string($entry) && preg_match('/^word\/(header|footer)\d+\.xml$/', $entry)) {
                    $part = SidangOfficeTemplate::readXml($zip, $entry);
                    $this->fillFields($part, $fields);
                    SidangOfficeTemplate::saveXml($zip, $entry, $part);
                }
            }
        });
    }

    private function fillRubrics(DOMDocument $xml, array $settings): void
    {
        $namespace = $xml->documentElement->namespaceURI;
        $query = new DOMXPath($xml);
        $query->registerNamespace('w', $namespace);
        foreach (['examiner', 'supervisor'] as $role) {
            $key = $role.'_rubric';
            if ($settings[$key] === SidangSettings::DEFAULTS[$key]) {
                continue;
            }
            $table = $query->query('//w:tbl[w:tblPr/w:tblCaption[@w:val="sita:rubric:'.$role.'"]]')->item(0);
            if ($table === null) {
                continue;
            }
            $rows = iterator_to_array($query->query('./w:tr', $table));
            $prototype = $rows[1];
            $total = $rows[count($rows) - 1];
            foreach (array_slice($rows, 1, -1) as $row) {
                $table->removeChild($row);
            }
            foreach (SidangSettings::rubric($settings[$key]) as $index => $component) {
                $row = $prototype->cloneNode(true);
                foreach ([($index + 1).'.', $component['label'], '', $component['weight'].' %', ''] as $column => $text) {
                    $cell = $query->query('./w:tc', $row)->item($column);
                    foreach (iterator_to_array($cell->childNodes) as $child) {
                        if ($child->localName !== 'tcPr') {
                            $cell->removeChild($child);
                        }
                    }
                    $paragraph = $xml->createElementNS($namespace, 'w:p');
                    $run = $xml->createElementNS($namespace, 'w:r');
                    $sourceCell = $query->query('./w:tc', $prototype)->item($column);
                    $properties = $query->query('.//w:rPr', $sourceCell)->item(0);
                    if ($properties !== null) {
                        $run->appendChild($properties->cloneNode(true));
                    }
                    $node = $xml->createElementNS($namespace, 'w:t');
                    $node->appendChild($xml->createTextNode($text));
                    $run->appendChild($node);
                    $paragraph->appendChild($run);
                    $cell->appendChild($paragraph);
                }
                $table->insertBefore($row, $total);
            }
        }
    }

    private function fillFields(DOMDocument $xml, array $fields): void
    {
        foreach ($xml->getElementsByTagNameNS($xml->documentElement->namespaceURI, 't') as $run) {
            if (str_contains($run->textContent, '{{')) {
                $text = preg_replace_callback('/\{\{(\w+)\}\}/', fn (array $match): string => $fields[$match[1]] ?? '', $run->textContent);
                $run->replaceChildren($xml->createTextNode($text));
                $run->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }
        }
    }
}
