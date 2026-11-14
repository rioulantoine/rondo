<?php
use PHPUnit\Framework\TestCase;
use Rondo\{Clock, MemoryStore, Team};

class TeamTest extends TestCase
{
    private MemoryStore $s;

    protected function setUp(): void
    {
        Clock::$frozen = new DateTimeImmutable('2026-11-15 10:00:00', new DateTimeZone('Europe/Paris'));
        $this->s = new MemoryStore();
        $this->s->data['config:sumo:team'] = [
            ['id' => 'm1', 'name' => 'Léa', 'pin' => '1234', 'permissions' => ['cuisine']],
            ['id' => 'm2', 'name' => 'Tom', 'pin' => '0000', 'zone' => 'salle_bar'],
        ];
    }

    public function testBonPinOuvreLaSession(): void
    {
        $r = Team::verifyPin($this->s, 'sumo', 'm1', '1234');
        $this->assertTrue($r['ok']);
        $this->assertSame(['cuisine'], Team::person($r['member'])['perms']);
    }

    public function testCinqErreursBloquentCinqMinutes(): void
    {
        for ($i = 4; $i >= 1; $i--) $this->assertSame($i, Team::verifyPin($this->s, 'sumo', 'm1', '9999')['attemptsLeft']);
        $this->assertTrue(Team::verifyPin($this->s, 'sumo', 'm1', '9999')['locked']);
        // même le bon PIN est refusé pendant le blocage
        $this->assertTrue(Team::verifyPin($this->s, 'sumo', 'm1', '1234')['locked']);
        Clock::$frozen = Clock::$frozen->modify('+5 minutes +1 second');
        $this->assertTrue(Team::verifyPin($this->s, 'sumo', 'm1', '1234')['ok']);
    }

    public function testLePinNeSortJamais(): void
    {
        $this->assertArrayNotHasKey('pin', Team::public(Team::all($this->s, 'sumo')[0]));
    }

    public function testAnciennesZonesV1(): void
    {
        $tom = Team::find($this->s, 'sumo', 'm2');
        $this->assertSame(['salle', 'bar', 'reservation'], $tom['permissions']);
        $this->assertSame(['salle', 'bar'], $tom['postes']);
    }

    public function testSlugCommeLaV1(): void
    {
        $this->assertSame('sumo-beach', Team::slugify('  Sumo Beach! '));
        $this->assertSame('chez-lulu-creperie', Team::slugify('Chez Lulu — Crêperie'));
    }
}
