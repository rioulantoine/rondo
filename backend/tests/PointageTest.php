<?php
use PHPUnit\Framework\TestCase;
use Rondo\{Clock, Pointage};

class PointageTest extends TestCase
{
    private array $lea = ['id' => 'm1', 'name' => 'Léa Martin'];
    private array $shifts = [['staff' => 'Léa Martin', 'date' => '2026-11-15', 'start' => '09:00', 'end' => '15:00']];

    private function at(string $time): void
    {
        Clock::$frozen = new DateTimeImmutable("2026-11-15 $time", new DateTimeZone('Europe/Paris'));
    }

    public function testArriveeDansLeCreneauAvecTolerance(): void
    {
        $this->at('08:56');
        $e = Pointage::build([], $this->lea, 'arrivee', $this->shifts);
        $this->assertSame('2026-11-15T07:56:00.000Z', $e['ts']);
        $this->assertSame(-4, $e['diffMin']);
        $this->assertArrayNotHasKey('offSchedule', $e);
    }

    public function testHorsPlanningDemandeUneJustificationSansBloquer(): void
    {
        $this->at('08:50');
        $this->assertSame('hors_planning', Pointage::build([], $this->lea, 'arrivee', $this->shifts)['error']);
        $e = Pointage::build([], $this->lea, 'arrivee', $this->shifts, ['justification' => 'Livraison à réceptionner']);
        $this->assertTrue($e['offSchedule']);
        $this->assertFalse($e['validated']);
    }

    public function testCreneauAChevalSurMinuit(): void
    {
        $this->at('23:30');
        $this->assertNotNull(Pointage::coveringShift([['staff' => 'Léa Martin', 'date' => '2026-11-15', 'start' => '19:00', 'end' => '01:00']], 'Léa Martin', Clock::now()));
    }

    public function testEnchainementEtPauses(): void
    {
        $this->at('09:00');
        $this->assertSame('depart_sans_arrivee', Pointage::build([], $this->lea, 'depart', $this->shifts)['error']);
        $events = [Pointage::build([], $this->lea, 'arrivee', $this->shifts)];
        $this->assertSame('double_arrivee', Pointage::build($events, $this->lea, 'arrivee', $this->shifts)['error']);
        $this->at('12:00');
        $events[] = $p = Pointage::build($events, $this->lea, 'pause', $this->shifts);
        $this->assertSame('pause_debut', $p['type']);
        $this->assertSame('pause', Pointage::status(Pointage::dayEvents($events, 'm1', '2026-11-15')));
        $this->at('12:30');
        $this->assertSame('pause_fin', Pointage::build($events, $this->lea, 'pause', $this->shifts)['type']);
    }

    public function testDistanceAuRestaurant(): void
    {
        $this->at('09:00');
        $e = Pointage::build([], $this->lea, 'arrivee', $this->shifts, ['lat' => 47.2500, 'lng' => -2.3400, 'restaurantLocation' => ['lat' => 47.2500, 'lng' => -2.3420]]);
        $this->assertGreaterThan(Pointage::GEO_ALERT_METERS, $e['distanceMeters']);
    }

    public function testForfaitJour(): void
    {
        $this->at('10:00');
        $this->assertSame('pas_forfait', Pointage::build([], $this->lea, 'jour_travaille', [])['error']);
        $cadre = $this->lea + ['forfaitJour' => true];
        $events = [Pointage::build([], $cadre, 'jour_travaille', [])];
        $this->assertSame('jour_deja_enregistre', Pointage::build($events, $cadre, 'jour_travaille', [])['error']);
    }
}
