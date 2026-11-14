<?php
use PHPUnit\Framework\TestCase;
use Rondo\{Clock, Haccp, MemoryStore};

class HaccpTest extends TestCase
{
    protected function setUp(): void
    {
        Clock::$frozen = new DateTimeImmutable('2026-11-15 08:12:00', new DateTimeZone('Europe/Paris'));
    }

    public function testReleveDansLesBornes(): void
    {
        $e = Haccp::temperature(['id' => 'frigo1', 'name' => 'Réfrigérateur 1', 'min' => 0, 'max' => 4], '2.8', '', '', 'Léa');
        $this->assertTrue($e['ok']);
        $this->assertSame('2026-11-15T07:12:00.000Z', $e['ts']);
        $this->assertSame('Avant service du matin', $e['period']);
    }

    public function testHorsBornesExigeUneActionCorrective(): void
    {
        $congel = ['id' => 'c', 'name' => 'Congélateur bar', 'min' => -25, 'max' => -18];
        $this->assertSame('Congélateur bar à −12 °C. Indique l\'action corrective.', Haccp::temperature($congel, -12, '', '', 'Tom')['error']);
        $e = Haccp::temperature($congel, -12, 'Porte mal fermée, relevé dans 30 min', '', 'Tom');
        $this->assertFalse($e['ok']);
    }

    public function testAlertesDlc(): void
    {
        $t = fn($id, $date, $extra = []) => ['id' => $id, 'type' => 'dlc', 'date' => $date] + $extra;
        $alerts = Haccp::dlcAlerts([
            $t('loin', '2026-11-25'),
            $t('j3', '2026-11-18'),
            $t('jour', '2026-11-15'),
            $t('depassee', '2026-11-13'),
            $t('jetee', '2026-11-14', ['removedAt' => 'x']),
            ['id' => 'lot', 'type' => 'lot', 'date' => '2026-11-14'],
        ]);
        $this->assertSame(['depassee', 'jour', 'j3'], array_map(fn($a) => $a['entry']['id'], $alerts));
        $this->assertSame([-2, 0, 3], array_column($alerts, 'daysLeft'));
    }

    public function testResumeDuJourAvecValeursParDefaut(): void
    {
        $s = new MemoryStore();
        $s->data['log:sumo:bar:tasks:2026-11-15'] = ['t1' => ['done' => true]];
        $s->data['log:sumo:bar:temp:2026-11'] = [
            ['ts' => '2026-11-15T07:00:00.000Z', 'ok' => false],
            ['ts' => '2026-11-14T07:00:00.000Z', 'ok' => false],
        ];
        $r = Haccp::today($s, 'sumo', 'bar');
        $this->assertSame([1, 9, 1, 1], [$r['tasksDone'], $r['tasksTotal'], $r['tempCount'], count($r['tempAlerts'])]);
    }
}
