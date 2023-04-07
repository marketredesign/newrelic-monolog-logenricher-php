<?php

/**
 * Copyright [2019] New Relic Corporation. All rights reserved.
 * SPDX-License-Identifier: Apache-2.0
 *
 * This file contains the tests for the New Relic Monolog Enricher
 * Processor. Tests must cover cases when a compatible New Relic
 * extension (v9.3 or higher) is not available
 *
 * @author New Relic PHP <php-agent@newrelic.com>
 *
 * Updated after fork: Modified tests after updates to source that add support for Monolog v3.
 */

namespace NewRelic\Monolog\Enricher {
    /**
     * Override the New Relic PHP Extension's `newrelic_get_linking_metadata()`
     * function to mock a response
     *
     * @return array
     */
    function newrelic_get_linking_metadata()
    {
        return array('hostname' => 'example.host',
                     'entity.name' => 'Processor Tests',
                     'entity.type' => 'SERVICE');
    }
}

namespace Unit {
    use Monolog\Level;
    use Monolog\LogRecord;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use function NewRelic\Monolog\Enricher\newrelic_get_linking_metadata;

    class ProcessorTest extends TestCase
    {
        /**
         * getMockedProcessor returns a mocked NewRelic\Monolog\Enricher\Processor
         * that is configured to return a set value in
         * `Processor::contextAvailable`. This allows testing scenarios where a
         * compatible New Relic extension (v9.3 or higher) is not available.
         *
         * @param bool $nr_ext_compat Whether a compatible extension was 'found'
         */
        private function getMockedProcessor($nr_ext_compat): MockObject
        {
            $proc = $this->getMockBuilder('NewRelic\Monolog\Enricher\Processor')
                ->onlyMethods(array('contextAvailable'))
                ->getMock();
            $proc->method('contextAvailable')
                ->willReturn($nr_ext_compat);

            return $proc;
        }

        /**
         * Tests that the array returned by `newrelic_get_linking_metadata()`
         * is inserted at `$logRecord['extra']['newrelic-context'] when a
         * compatible New Relic extension is loaded
         */
        public function testInvoke()
        {
            $input = new LogRecord(
                new \DateTimeImmutable("now", new \DateTimeZone("UTC")),
                'test',
                Level::Warning,
                'test',
                [],
                [],
            );

            $proc = $this->getMockedProcessor(true);
            $enriched_record = $proc($input);

            $expected = newrelic_get_linking_metadata();
            $got = $enriched_record['extra']['newrelic-context'];
            $this->assertSame($expected, $got);
        }

        /**
         * Tests that the given Monolog record is returned unchanged when a
         * compatible New Relic extension is not loaded
         */
        public function testInputPassthroughWhenNewRelicNotLoaded()
        {
            $input = new LogRecord(
                new \DateTimeImmutable("now", new \DateTimeZone("UTC")),
                'test',
                Level::Warning,
                'test',
                [],
                [],
            );

            $proc = $this->getMockedProcessor(false);

            $this->assertSame($input, $proc($input));
        }
    }
}
