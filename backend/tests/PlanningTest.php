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
}
