<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services;

use SimpleXMLElement;
use InvalidArgumentException;

final class XmlHandler
{
    public function parseConfig(string $xmlString): array
    {
        $xml = $this->loadXml($xmlString);

        return [
            'context' => $this->extractTagContent($xml, 'context'),
            'guidelines' => $this->extractTagContent($xml, 'guidelines'),
            'examples' => $this->extractExamples($xml),
            'output_format' => $this->extractTagContent($xml, 'output_format'),
            'best_practices' => $this->extractTagContent($xml, 'best_practices')
        ];
    }

    public function appendOutput(string $xmlOutput, string $output, int $feedbackScore): string
    {
        $xml = $this->loadXml($xmlOutput);
        $outputCount = count($xml->xpath('//output-*')) + 1;

        $newOutput = $xml->addChild("output-{$outputCount}");
        $newOutput->addChild('content', htmlspecialchars($output));
        $newOutput->addChild('feedback', (string) $feedbackScore);
        $newOutput->addChild('timestamp', now()->toIso8601String());

        return $xml->asXML();
    }

    private function loadXml(string $xmlString): SimpleXMLElement
    {
        try {
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($xmlString);
            libxml_clear_errors();
            return $xml;
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid XML string: {$e->getMessage()}");
        }
    }

    private function extractTagContent(SimpleXMLElement $xml, string $tag): array
    {
        $content = [];
        $elements = $xml->xpath("//{$tag}");

        foreach ($elements as $element) {
            $key = (string) ($element['name'] ?? count($content));
            $content[$key] = (string) $element;
        }

        return $content;
    }

    private function extractExamples(SimpleXMLElement $xml): array
    {
        $examples = [];
        $exampleElements = $xml->xpath('//example');

        foreach ($exampleElements as $example) {
            $examples[] = [
                'input' => (string) $example->input,
                'output' => (string) $example->output
            ];
        }

        return $examples;
    }

    public function analyzeOutputHistory(string $xmlOutput): array
    {
        $xml = $this->loadXml($xmlOutput);
        $outputs = $xml->xpath('//output-*');

        $analysis = [
            'total_outputs' => count($outputs),
            'average_feedback' => 0,
            'feedback_trend' => [],
            'high_performing_patterns' => []
        ];

        $feedbackScores = [];
        foreach ($outputs as $output) {
            $feedbackScore = (int) $output->feedback;
            $feedbackScores[] = $feedbackScore;

            if ($feedbackScore >= 4) {
                $analysis['high_performing_patterns'][] = [
                    'content' => (string) $output->content,
                    'score' => $feedbackScore
                ];
            }
        }

        if (!empty($feedbackScores)) {
            $analysis['average_feedback'] = array_sum($feedbackScores) / count($feedbackScores);
            $analysis['feedback_trend'] = $this->calculateTrend($feedbackScores);
        }

        return $analysis;
    }

    private function calculateTrend(array $scores): array
    {
        $trend = [];
        $windowSize = 5;

        for ($i = 0; $i < count($scores) - $windowSize + 1; $i++) {
            $window = array_slice($scores, $i, $windowSize);
            $trend[] = array_sum($window) / count($window);
        }

        return $trend;
    }
}
