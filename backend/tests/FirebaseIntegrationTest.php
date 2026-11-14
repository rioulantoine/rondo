<?php
use PHPUnit\Framework\TestCase;
use Rondo\{FirebaseAuth, Firestore};

/** Tourne seulement avec les émulateurs Firebase (CI ou `npm run emulators` en local). */
class FirebaseIntegrationTest extends TestCase
{
    private Firestore $fs;

    protected function setUp(): void
    {
        if (!getenv('FIRESTORE_EMULATOR_HOST')) $this->markTestSkipped('Émulateurs Firebase non démarrés');
        // « owner » = jeton administrateur de l'émulateur
        $this->fs = new Firestore('demo-rondo', fn() => 'owner', getenv('FIRESTORE_EMULATOR_HOST'));
    }

    public function testLectureEcritureFormatV1(): void
    {
        $key = 'config:it' . uniqid() . ':cuisine/equipments';
        $this->assertNull($this->fs->get($key));
        $this->fs->set($key, [['id' => 'frigo1', 'name' => 'Réfrigérateur 1', 'min' => 0, 'max' => 4]]);
        $this->assertSame('Réfrigérateur 1', $this->fs->get($key)[0]['name']);
    }

    public function testAjoutsSimultanesSansPerte(): void
    {
        $code = 'it' . uniqid();
        $key = "log:$code:pointage:2026-11";
        foreach (range(1, 5) as $i) $this->fs->mutate($key, fn($a) => [...$a, $i], []);
        $this->assertSame([1, 2, 3, 4, 5], $this->fs->get($key));
        $this->assertContains($key, $this->rawKeyIndex($code));
    }

    public function testAuthInscriptionPuisConnexion(): void
    {
        $auth = new FirebaseAuth('fake-key', getenv('FIREBASE_AUTH_EMULATOR_HOST'));
        $email = uniqid() . '@resto.checktonresto.local';
        $this->assertNotNull($auth->signUp($email, 'secret123'));
        $this->assertNotNull($auth->signIn($email, 'secret123'));
        $this->assertNull($auth->signIn($email, 'mauvais'));
    }

    private function rawKeyIndex(string $code): array
    {
        [, $d] = \Rondo\Http::send('GET', 'http://' . getenv('FIRESTORE_EMULATOR_HOST') . "/v1/projects/demo-rondo/databases/(default)/documents/checkTaCuisineData/keyIndex:$code", ['Authorization: Bearer owner']);
        return array_column($d['fields']['keys']['arrayValue']['values'] ?? [], 'stringValue');
    }
}
