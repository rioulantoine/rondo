<?php
use PHPUnit\Framework\TestCase;
use Rondo\{MemoryStore, Planning};

class PlanningTest extends TestCase
{
    public function testSemaineCommenceLeLundi(): void
    {
        $this->assertSame('2026-11-09', Planning::weekStart('2026-11-15')); // dimanche
        $this->assertSame('2026-11-16', Planning::weekStart('2026-11-16'));
    }

    public function testSeulsLesCreneauxPubliesSontVisibles(): void
    {
        $s = new MemoryStore();
        $s->data['log:r:planning:2026-11'] = [
            ['staff' => 'Léa', 'date' => '2026-11-15', 'start' => '09:00'],
            ['staff' => 'Léa', 'date' => '2026-11-16', 'start' => '09:00'],
            ['staff' => 'Tom', 'date' => '2026-11-15', 'start' => '18:00'],
        ];
        $s->data['config:r:planningPublish'] = ['2026-11-09' => ['publishedAt' => 'x']];
        $this->assertSame(['2026-11-15'], array_column(Planning::myShifts($s, 'r', 'Léa', '2026-11-01', '2026-11-30'), 'date'));
    }

    public function testAlertesLegales(): void
    {
        $sh = fn($date, $start, $end) => ['staff' => 'Tom', 'date' => $date, 'start' => $start, 'end' => $end];
        $alerts = Planning::legalAlerts([
            $sh('2026-11-16', '18:00', '01:00'),  // à cheval sur minuit
            $sh('2026-11-17', '09:00', '15:00'),  // 8 h de repos seulement
            $sh('2026-11-17', '18:00', '23:30'),  // coupure : 11 h 30 dans la journée
        ]);
        $this->assertSame(['daily', 'rest'], array_column($alerts, 'rule'));
        $this->assertSame('Tom : 8 h de repos avant le 17/11 (min 11 h).', $alerts[1]['message']);
    }

    public function testQuaranteHuitHeures(): void
    {
        $week = array_map(fn($d) => ['staff' => 'Léa', 'date' => "2026-11-$d", 'start' => '08:00', 'end' => '18:00'], [16, 17, 18, 19, 20]);
        $this->assertSame(['weekly'], array_column(Planning::legalAlerts($week), 'rule')); // 5 × 10 h = 50 h
        $this->assertSame([], Planning::legalAlerts(array_slice($week, 0, 4)));
    }

    public function testInstantaneDePublication(): void
    {
        $shifts = [['staff' => 'Léa', 'date' => '2026-11-16', 'start' => '09:00', 'end' => '15:00']];
        $rec = ['2026-11-16' => ['snapshot' => Planning::snapshot($shifts)]];
        $this->assertTrue(Planning::publishStatus($rec, '2026-11-16', $shifts)['upToDate']);
        $shifts[0]['end'] = '16:00';
        $this->assertFalse(Planning::publishStatus($rec, '2026-11-16', $shifts)['upToDate']);
    }
}
