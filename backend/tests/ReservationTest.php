<?php
use PHPUnit\Framework\TestCase;
use Rondo\{Clock, Reservation};

class ReservationTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        Clock::$frozen = new DateTimeImmutable('2026-11-15 12:20:00', new DateTimeZone('Europe/Paris'));
        $this->config = Reservation::defaultConfig('Sumo Beach');
    }

    public function testCreneauxDeQuinzeMinutes(): void
    {
        $slots = Reservation::allSlots($this->config);
        $this->assertSame(['12:00', '12:15', '12:30', '12:45', '13:00', '13:15', '13:30', '13:45', '19:00'], array_slice($slots, 0, 9));
        $this->assertSame('21:45', end($slots));
    }

    public function testDisponibilitesAvecPlafondFermetureEtHeurePassee(): void
    {
        $this->config['slotMax'] = ['19:00' => 1, '19:15' => 0];
        $this->config['closedSlots'] = ['2026-11-15' => ['19:30']];
        $avail = [['id' => 'a', 'date' => '2026-11-15', 'slot' => '19:00', 'party' => 2, 'status' => 'confirmee']];
        $slots = array_column(Reservation::availability($this->config, [['id' => 't1'], ['id' => 't2']], $avail, '2026-11-15', 2), 'count', 'slot');
        $this->assertArrayNotHasKey('12:15', $slots);       // déjà passé
        $this->assertSame(2, $slots['12:30']);  // 2 tables, aucune prise
        $this->assertSame(0, $slots['19:00']);             // plafond 1 atteint
        $this->assertArrayNotHasKey('19:15', $slots);       // jamais ouvert
        $this->assertArrayNotHasKey('19:30', $slots);       // fermeture ponctuelle
    }

    public function testValidationDeLaDemande(): void
    {
        $ok = ['date' => '2026-11-16', 'slot' => '19:00', 'party' => 4, 'firstName' => 'Ana', 'lastName' => 'Petit', 'phone' => '06 11 22 33 44', 'email' => 'ana@example.com'];
        $r = Reservation::build($ok, $this->config);
        $this->assertSame('confirmee', $r['status']);
        $this->assertSame(32, strlen($r['cancelToken']));
        $this->assertArrayNotHasKey('email', Reservation::light($r));
        $this->assertArrayHasKey('error', Reservation::build(['party' => 13] + $ok, $this->config));
        $this->assertArrayHasKey('error', Reservation::build(['slot' => '16:00'] + $ok, $this->config));
        $this->assertArrayHasKey('error', Reservation::build(['date' => '2026-11-14'] + $ok, $this->config));
        $this->assertArrayHasKey('error', Reservation::build(['email' => 'pas-un-mail'] + $ok, $this->config));
    }
}
