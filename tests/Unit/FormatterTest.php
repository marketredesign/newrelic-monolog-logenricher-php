<?php

/**
 * Copyright [2019] New Relic Corporation. All rights reserved.
 * SPDX-License-Identifier: Apache-2.0
 *
 * This file contains the tests for the New Relic Monolog Enricher
 * JSON Formatter.
 *
 * @author New Relic PHP <php-agent@newrelic.com>
 *
 * Updated after fork: Modified tests after updates to source that add support for Monolog v3.
 */

namespace Unit;

use DateTimeImmutable;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use NewRelic\Monolog\Enricher\Formatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{

    /**
     * Generates a Monolog record that optionally contains New Relic
     * context information (enabled by default)
     *
     * @param bool $withNrContext
     * @return array
     */
    private function getRecord($withNrContext)
    {
        $record = new LogRecord(
            new DateTimeImmutable("now", new \DateTimeZone("UTC")),
            'test',
            Level::Warning,
            'test',
            [],
            [],
        );

        if ($withNrContext) {
            $nr_context = array(
                'hostname' => 'example.host',
                'entity.name' => 'Processor Tests',
                'entity.type' => 'SERVICE',
                'trace.id' => 'aabb1234AABB4321',
                'span.id' => 'wxyz9876WXYZ6789'
            );
            $record['extra']['newrelic-context'] = $nr_context;
        }

        return $record;
    }

    /**
     * Generates the expected string for a given record after formatting.
     * Optionally appends a trailing newline (enabled by default)
     *
     * @param array $record
     * @param bool $appendNewline
     * @return array
     */
    private function getExpectedForRecord($record, $appendNewline = true)
    {
        $expected =
        '{"message":"test",'
        . '"context":' . '{}' . ','
        . '"level":300,"level_name":"WARNING","channel":"test",'
        . '"extra":' . '{}' . ','
        . '"datetime":' . json_encode($record['datetime']) . ',';

        if (isset($record['extra']['newrelic-context'])) {
            $expected = $expected . '"hostname":"example.host",'
                . '"entity.name":"Processor Tests","entity.type":"SERVICE",'
                . '"trace.id":"aabb1234AABB4321","span.id":"wxyz9876WXYZ6789",';
        }

        $expected .= '"timestamp":' . (int)($record['datetime']->format('U.u') * 1000) . '}' . ($appendNewline ? "\n" : '');

        return $expected;
    }

    /**
     * Verifies constructor sets expected parameters and respects overrides
     */
    public function testConstruct()
    {
        // Verify default parameters
        $formatter = new Formatter();
        $this->assertEquals(
            Formatter::BATCH_MODE_NEWLINES,
            $formatter->getBatchMode()
        );
        $this->assertEquals(true, $formatter->isAppendingNewlines());

        // Verify that batch mode can be set, and trailing newlines can
        // be disabled
        $formatter = new Formatter(Formatter::BATCH_MODE_JSON, false);
        $this->assertEquals(
            Formatter::BATCH_MODE_JSON,
            $formatter->getBatchMode()
        );
        $this->assertEquals(false, $formatter->isAppendingNewlines());
    }

    /**
     * Tests format which in turn calls overridden normalize method containing
     * the New Relic transformations
     */
    public function testFormat()
    {
        // Test with trailing newline
        $formatter = new Formatter();
        $record = $this->getRecord(true);
        $this->assertEquals(
            self::sortData($this->getExpectedForRecord($record)),
            self::sortData($formatter->format($record)),
        );

        // Test without trailing newline
        $formatter = new Formatter(JsonFormatter::BATCH_MODE_NEWLINES, false);
        $this->assertEquals(
            self::sortData($this->getExpectedForRecord($record, false)),
            self::sortData($formatter->format($record)),
        );

        // Test without New Relic context information
        $formatter = new Formatter();
        $record = $this->getRecord(false);
        $this->assertEquals(
            self::sortData($this->getExpectedForRecord($record)),
            self::sortData($formatter->format($record)),
        );
    }

    /**
     * Tests that batch records are processed correctly according to
     * $batchMode parameter
     */
    public function testFormatBatch()
    {
        $formatter = new Formatter(JsonFormatter::BATCH_MODE_JSON, false);
        // One record with New Relic context information, one without
        $records = array(
            $this->getRecord(true),
            $this->getRecord(false),
        );

        $this->assertEquals(
            self::sortData(
                sprintf(
                    '[%s,%s]',
                    $this->getExpectedForRecord($records[0], false),
                    $this->getExpectedForRecord($records[1], false),
                )
            ),
            self::sortData($formatter->formatBatch($records)),
        );

        $formatter = new Formatter();
        // One record with New Relic context information, one without
        $records = array(
            $this->getRecord(true),
            $this->getRecord(false),
        );

        $actualData = explode("\n", $formatter->formatBatch($records));

        $this->assertEquals(
            sprintf(
                '%s%s',
                self::sortData($this->getExpectedForRecord($records[0])),
                self::sortData($this->getExpectedForRecord($records[1], false)),
            ),
            // Separate entries by newline, however do not append final newline
            // to match Monolog\JsonFormatter::formatBatchNewlines behavior
            self::sortData($actualData[0]) . self::sortData($actualData[1]),
        );
    }

    private static function sortData(array|string $data): array|string {
        if (is_string($data)) {
            $actualData = json_decode($data, true);

            self::ksortRecursive($actualData);

            return json_encode($actualData);
        }

        self::ksortRecursive($data);

        return $data;
    }

    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }

        ksort($array);
    }
}
