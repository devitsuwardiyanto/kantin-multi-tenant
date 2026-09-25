<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pertemuan 14: RTM (`docs/REQUIREMENTS_TRACEABILITY.md`) menjadi artefak yang diuji. Setiap
 * use case SRS v2 (UC-01 s.d. UC-23) wajib tercatat tepat satu kali, berstatus PASS, dan setiap
 * test penerimaan yang dirujuk benar-benar ada di folder tests — RTM tidak boleh basi.
 */
class RequirementsTraceabilityTest extends TestCase
{
    /** @return array<string, array{requirement: string, tests: string, status: string}> */
    private function rows(): array
    {
        $rows = [];
        foreach (file(base_path('docs/REQUIREMENTS_TRACEABILITY.md')) ?: [] as $line) {
            if (! preg_match('/^\| (UC-\d{2}) [^|]+\| (FR-[A-Z]+-\d{2}) \| [^|]+\| [^|]+\| ([^|]+)\| (\w+) \|$/u', trim($line), $match)) {
                continue;
            }
            $this->assertArrayNotHasKey($match[1], $rows, $match[1].' tercatat lebih dari sekali');
            $rows[$match[1]] = ['requirement' => $match[2], 'tests' => $match[3], 'status' => $match[4]];
        }

        return $rows;
    }

    public function test_all_23_use_cases_are_traced_and_passed(): void
    {
        $rows = $this->rows();
        $expected = array_map(fn (int $n): string => sprintf('UC-%02d', $n), range(1, 23));

        $this->assertEqualsCanonicalizing($expected, array_keys($rows));
        foreach ($rows as $useCase => $row) {
            $this->assertSame('PASS', $row['status'], $useCase.' belum PASS');
        }
        $this->assertCount(23, array_unique(array_column($rows, 'requirement')), 'setiap use case memetakan kebutuhan FR yang berbeda');
    }

    public function test_every_referenced_acceptance_test_exists(): void
    {
        foreach ($this->rows() as $useCase => $row) {
            preg_match_all('/\b([A-Z][A-Za-z]+Test)\b/', $row['tests'], $names);
            $this->assertNotEmpty($names[1], $useCase.' tidak merujuk test otomatis');

            foreach ($names[1] as $name) {
                $exists = file_exists(base_path("tests/Feature/{$name}.php"))
                    || file_exists(base_path("tests/Feature/Auth/{$name}.php"))
                    || file_exists(base_path("tests/Unit/{$name}.php"));
                $this->assertTrue($exists, "{$useCase}: {$name} tidak ditemukan di tests/");
            }
        }
    }
}
