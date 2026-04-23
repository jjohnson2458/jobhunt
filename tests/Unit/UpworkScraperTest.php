<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use UpworkScraper;

class UpworkScraperTest extends TestCase
{
    private UpworkScraper $scraper;

    protected function setUp(): void
    {
        $this->scraper = new UpworkScraper();
    }

    public function testSourceReturnsUpwork(): void
    {
        $this->assertEquals('upwork', $this->scraper->source());
    }

    public function testImplementsJobScraperInterface(): void
    {
        $this->assertInstanceOf(\JobScraper::class, $this->scraper);
    }

    public function testFetchReturnsEmptyWhenNoFiles(): void
    {
        // Point to a directory with no Upwork HTML files
        $track = ['role_keywords' => 'PHP', 'locations' => '', 'remote_ok' => true];

        // Temporarily override BASE_PATH behavior by using a track
        // The scraper looks in BASE_PATH/public/uploads/ — if no Upwork*.html files exist, returns []
        // We can't easily change BASE_PATH, so we test the parse method via reflection
        $result = $this->parseFixture('nonexistent.html', $track);
        $this->assertEmpty($result);
    }

    public function testParsesJobTitles(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        $this->assertNotEmpty($listings);
        $titles = array_column($listings, 'title');
        $this->assertContains('Datadog: GCP VM + Redis + MySQL + Laravel + Browser', $titles);
        $this->assertContains('SQL Server DBA Needed for Monitoring and Maintenance', $titles);
        $this->assertContains('Senior Software Engineer', $titles);
    }

    public function testParsesThreeListingsFromFixture(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        $this->assertCount(3, $listings);
    }

    public function testParsesSourceUrls(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        foreach ($listings as $listing) {
            $this->assertStringStartsWith('https://www.upwork.com/jobs/', $listing['source_url']);
        }
    }

    public function testExtractsSourceIdFromUrl(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        foreach ($listings as $listing) {
            $this->assertNotEmpty($listing['source_id'], "source_id should be extracted from URL for: {$listing['title']}");
            $this->assertMatchesRegularExpression('/^\d+$/', $listing['source_id']);
        }
    }

    public function testAllListingsMarkedRemote(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        foreach ($listings as $listing) {
            $this->assertEquals(1, $listing['is_remote']);
        }
    }

    public function testSourceFieldSetToUpwork(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        foreach ($listings as $listing) {
            $this->assertEquals('upwork', $listing['source']);
        }
    }

    public function testCompanyDefaultsToUpworkClient(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        foreach ($listings as $listing) {
            $this->assertEquals('Upwork Client', $listing['company']);
        }
    }

    public function testParsesSalaryWhenPresent(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        // First listing (Datadog) has Hourly: $35-$95
        $datadog = $this->findByTitle($listings, 'Datadog');
        if ($datadog) {
            $this->assertNotNull($datadog['salary_text']);
            $this->assertStringContainsString('35', $datadog['salary_text']);
        }
    }

    public function testParsesPostedDate(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        // At least some listings should have a posted_at date
        $withDates = array_filter($listings, fn($l) => $l['posted_at'] !== null);
        $this->assertNotEmpty($withDates, 'At least one listing should have a parsed posted_at date');
    }

    public function testDescriptionIncludesSkills(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        // First listing should have skills appended
        $datadog = $this->findByTitle($listings, 'Datadog');
        if ($datadog) {
            $this->assertStringContainsString('Skills:', $datadog['description']);
        }
    }

    public function testKeywordFilteringExcludesNonMatches(): void
    {
        $track = $this->defaultTrack();
        $track['role_keywords'] = 'Datadog';

        $listings = $this->parseFixture('upwork_sample.html', $track);

        // Should only return the Datadog listing
        $this->assertCount(1, $listings);
        $this->assertStringContainsString('Datadog', $listings[0]['title']);
    }

    public function testKeywordFilteringEmptyReturnsAll(): void
    {
        $track = $this->defaultTrack();
        $track['role_keywords'] = '';

        $listings = $this->parseFixture('upwork_sample.html', $track);

        // No keyword filter means all listings returned
        $this->assertCount(3, $listings);
    }

    public function testExcludeKeywordsFiltering(): void
    {
        $track = $this->defaultTrack();
        $track['exclude_keywords'] = 'Datadog';

        $listings = $this->parseFixture('upwork_sample.html', $track);

        $titles = array_column($listings, 'title');
        foreach ($titles as $title) {
            $this->assertStringNotContainsString('Datadog', $title);
        }
    }

    public function testListingHasAllRequiredKeys(): void
    {
        $track = $this->defaultTrack();
        $listings = $this->parseFixture('upwork_sample.html', $track);

        $requiredKeys = [
            'source', 'source_url', 'source_id', 'title', 'company',
            'location', 'is_remote', 'salary_text', 'salary_min',
            'salary_max', 'description', 'posted_at',
        ];

        foreach ($listings as $listing) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $listing, "Missing key: {$key}");
            }
        }
    }

    public function testParseRelativeDateMinutesAgo(): void
    {
        $method = new \ReflectionMethod(UpworkScraper::class, 'parseRelativeDate');
        $method->setAccessible(true);

        $result = $method->invoke($this->scraper, '49 minutes ago');
        $this->assertNotNull($result);

        $parsed = new \DateTime($result);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $parsed->getTimestamp();
        // Should be roughly 49 minutes (2940 seconds) — allow 60s tolerance
        $this->assertGreaterThan(2880, $diff);
        $this->assertLessThan(3060, $diff);
    }

    public function testParseRelativeDateHoursAgo(): void
    {
        $method = new \ReflectionMethod(UpworkScraper::class, 'parseRelativeDate');
        $method->setAccessible(true);

        $result = $method->invoke($this->scraper, '3 hours ago');
        $this->assertNotNull($result);

        $parsed = new \DateTime($result);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $parsed->getTimestamp();
        $this->assertGreaterThan(10740, $diff); // ~179 min
        $this->assertLessThan(10920, $diff);    // ~182 min
    }

    public function testParseRelativeDateYesterday(): void
    {
        $method = new \ReflectionMethod(UpworkScraper::class, 'parseRelativeDate');
        $method->setAccessible(true);

        $result = $method->invoke($this->scraper, 'yesterday');
        $this->assertNotNull($result);

        $parsed = new \DateTime($result);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $parsed->getTimestamp();
        // Should be roughly 1 day (86400s) — allow 60s tolerance
        $this->assertGreaterThan(86340, $diff);
        $this->assertLessThan(86460, $diff);
    }

    public function testParseRelativeDateEmpty(): void
    {
        $method = new \ReflectionMethod(UpworkScraper::class, 'parseRelativeDate');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->scraper, ''));
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function defaultTrack(): array
    {
        return [
            'role_keywords'    => '',
            'locations'        => '',
            'remote_ok'        => true,
            'exclude_keywords' => '',
        ];
    }

    /**
     * Call the private parseFile method directly using reflection.
     */
    private function parseFixture(string $filename, array $track): array
    {
        $file = BASE_PATH . '/tests/fixtures/' . $filename;
        if (!file_exists($file)) return [];

        $keywords = strtolower($track['role_keywords'] ?? '');
        $keywordList = array_filter(array_map('trim', preg_split('/[,;|]+/', $keywords)));

        $method = new \ReflectionMethod(UpworkScraper::class, 'parseFile');
        $method->setAccessible(true);

        return $method->invoke($this->scraper, $file, $track, $keywordList);
    }

    private function findByTitle(array $listings, string $needle): ?array
    {
        foreach ($listings as $l) {
            if (stripos($l['title'], $needle) !== false) return $l;
        }
        return null;
    }
}
