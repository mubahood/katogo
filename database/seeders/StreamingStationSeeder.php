<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StreamingStationSeeder extends Seeder
{
    /**
     * Seed TV and Radio streaming stations.
     * IDEMPOTENT — safe to run multiple times. Skips existing stations by slug.
     */
    public function run(): void
    {
        $this->command->info('📡 Starting Streaming Station Seeder...');

        $tvStations = $this->getTvStations();
        $radioStations = $this->getRadioStations();

        $created = 0;
        $skipped = 0;

        foreach (array_merge($tvStations, $radioStations) as $station) {
            $slug = Str::slug($station['name']);

            if (DB::table('streaming_stations')->where('slug', $slug)->exists()) {
                $skipped++;
                continue;
            }

            $stationId = DB::table('streaming_stations')->insertGetId([
                'name' => $station['name'],
                'slug' => $slug,
                'type' => $station['type'],
                'category' => $station['category'],
                'frequency' => $station['frequency'] ?? null,
                'description' => $station['description'] ?? null,
                'logo_url' => $station['logo_url'] ?? null,
                'country' => 'Uganda',
                'language' => $station['language'] ?? 'English',
                'region' => $station['region'] ?? null,
                'website_url' => $station['website_url'] ?? null,
                'sort_order' => $station['sort_order'] ?? 0,
                'votes' => $station['votes'] ?? 0,
                'listeners_count' => 0,
                'status' => 'Active',
                'is_featured' => $station['is_featured'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert streaming URLs
            foreach ($station['urls'] as $i => $urlData) {
                DB::table('streaming_urls')->insert([
                    'streaming_station_id' => $stationId,
                    'url' => $urlData['url'],
                    'label' => $urlData['label'] ?? 'Main',
                    'format' => $urlData['format'] ?? null,
                    'quality' => $urlData['quality'] ?? null,
                    'bitrate' => $urlData['bitrate'] ?? null,
                    'cdn_provider' => $urlData['cdn_provider'] ?? null,
                    'referrer_url' => $urlData['referrer_url'] ?? null,
                    'is_default' => $i === 0,
                    'needs_token_refresh' => $urlData['needs_token_refresh'] ?? false,
                    'status' => $urlData['status'] ?? 'Active',
                    'sort_order' => $i,
                    'notes' => $urlData['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created++;
        }

        $this->command->info("✅ Streaming stations seeded: {$created} created, {$skipped} skipped (already exist).");
    }

    private function getTvStations(): array
    {
        return [
            // ── Section 1: Currently Active (Verified Live) ──
            [
                'name' => 'Ark TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Ugandan religious television channel',
                'logo_url' => 'https://i.imgur.com/tJBh9yt.png',
                'sort_order' => 1,
                'votes' => 100,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/arktv-international/index.fmp4.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Hyde Innovations'],
                ],
            ],
            [
                'name' => 'Alpha Digital TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Alpha Digital Television Uganda',
                'logo_url' => 'https://i.imgur.com/oYN6Q1O.png',
                'sort_order' => 2,
                'votes' => 80,
                'is_featured' => false,
                'urls' => [
                    ['url' => 'https://streamfi-alphatvdgtl1.zettawiseroutes.com:8181/hls/stream.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ZettaWise Routes'],
                ],
            ],
            [
                'name' => 'BTV Uganda',
                'type' => 'tv',
                'category' => 'Entertainment',
                'description' => 'BTV Uganda entertainment channel',
                'logo_url' => 'https://i.imgur.com/5V3BSUI.png',
                'sort_order' => 3,
                'votes' => 70,
                'is_featured' => false,
                'urls' => [
                    ['url' => 'https://streamfi-alphadgtl1.zettawiseroutes.com:8181/hls/stream.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ZettaWise Routes'],
                ],
            ],
            [
                'name' => 'Bukedde TV 1',
                'type' => 'tv',
                'category' => 'General',
                'language' => 'Luganda',
                'description' => 'Uganda\'s most popular Luganda TV channel by Vision Group',
                'logo_url' => 'https://i.imgur.com/0PbHxKP.png',
                'sort_order' => 4,
                'votes' => 500,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/bukedde1flussonic/index.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'Hyde Innovations'],
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bukkede1/playlist.m3u8', 'label' => 'Backup (UVO)', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive', 'notes' => 'Previously working via UVO TV, currently 403'],
                ],
            ],
            [
                'name' => 'Bukedde TV 2',
                'type' => 'tv',
                'category' => 'General',
                'language' => 'Luganda',
                'description' => 'Bukedde TV 2 - Second Luganda channel by Vision Group',
                'logo_url' => 'https://i.imgur.com/KQLAFIZ.png',
                'sort_order' => 5,
                'votes' => 400,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/bukedde2flussonic/index.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'Hyde Innovations'],
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bukkede2/playlist.m3u8', 'label' => 'Backup (UVO)', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Dream TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Dream TV Uganda - Religious broadcasting',
                'logo_url' => 'https://i.imgur.com/zCHhCaQ.png',
                'sort_order' => 6,
                'votes' => 60,
                'urls' => [
                    ['url' => 'https://streamfi-dreamtv1.zettawiseroutes.com:8181/hls/stream.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ZettaWise Routes'],
                ],
            ],
            [
                'name' => 'FORT TV',
                'type' => 'tv',
                'category' => 'Entertainment',
                'description' => 'FORT TV - Uganda entertainment channel',
                'logo_url' => 'https://i.imgur.com/oP50aDT.png',
                'sort_order' => 7,
                'votes' => 50,
                'urls' => [
                    ['url' => 'https://fort.co-works.org/memfs/87017643-274a-4bc0-a786-7767a0d159c2.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'Co-Works'],
                ],
            ],
            [
                'name' => 'Praise Jesus Tower TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Christian broadcasting from Uganda',
                'logo_url' => 'https://i.imgur.com/DlHNMrd.png',
                'sort_order' => 8,
                'votes' => 40,
                'urls' => [
                    ['url' => 'https://vsrv1.az-streamingserver.com:3555/live/dyjoqlgklive.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'AZ Streaming Server'],
                ],
            ],
            [
                'name' => 'Ramogi TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Ramogi TV - Kenyan/East African general channel available in Uganda',
                'logo_url' => 'https://i.imgur.com/DSLKP0S.png',
                'sort_order' => 9,
                'votes' => 200,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://citizentv.castr.com/5ea49827ff3b5d7b22708777/live_9b761ff063f511eca12909b8ef1524b4/index.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Castr'],
                ],
            ],
            [
                'name' => 'TV West',
                'type' => 'tv',
                'category' => 'General',
                'region' => 'Western Uganda',
                'description' => 'TV West - Western Uganda broadcasting',
                'logo_url' => 'https://i.imgur.com/HpQXWMa.png',
                'sort_order' => 10,
                'votes' => 150,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/tvwest-flussonic/index.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Hyde Innovations'],
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/tvwest/playlist.m3u8', 'label' => 'Backup (UVO)', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Wan Luo TV',
                'type' => 'tv',
                'category' => 'General',
                'language' => 'Luo',
                'description' => 'Wan Luo TV - Luo language broadcasting',
                'logo_url' => 'https://i.imgur.com/vbPjKgS.png',
                'sort_order' => 11,
                'votes' => 90,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/luotv-flussonic/index.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'Hyde Innovations'],
                ],
            ],

            // ── Section 2: Intermittent Streams ──
            [
                'name' => '3ABN TV Uganda',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => '3ABN broadcast for Uganda - not 24/7',
                'logo_url' => 'https://i.imgur.com/aFfOCTp.png',
                'sort_order' => 20,
                'votes' => 30,
                'urls' => [
                    ['url' => 'https://3abn.bozztv.com/3abn/3abn_uganda_live/index.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'BozzTV', 'status' => 'Intermittent', 'notes' => 'Not 24/7'],
                ],
            ],
            [
                'name' => 'Galaxy TV Uganda',
                'type' => 'tv',
                'category' => 'Music',
                'description' => 'Galaxy TV Uganda - Music and entertainment',
                'logo_url' => 'https://i.imgur.com/J7FWIc6.png',
                'sort_order' => 21,
                'votes' => 120,
                'urls' => [
                    ['url' => 'https://stream.castr.com/6463248048d6cd3e143655b2/live_43351ad0f3b411ed81c78fcc31887c54/index.fmp4.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Castr', 'referrer_url' => 'https://player.castr.com/live_43351ad0f3b411ed81c78fcc31887c54', 'status' => 'Intermittent', 'notes' => 'Requires referrer header'],
                    ['url' => 'https://streamfi-galaxytv1.zettawiseroutes.com:8181/hls/stream.m3u8', 'label' => 'Backup', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ZettaWise Routes', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Salt TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Salt Television - Christian broadcasting',
                'logo_url' => 'https://i.imgur.com/EKLqtAH.png',
                'sort_order' => 22,
                'votes' => 35,
                'urls' => [
                    ['url' => 'https://live.salttelevision.com/app/stream/abr.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Salt TV Direct', 'status' => 'Intermittent', 'notes' => 'Not 24/7'],
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/salttv/playlist.m3u8', 'label' => 'Backup (UVO)', 'format' => 'hls', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Gugudde TV',
                'type' => 'tv',
                'category' => 'Religious',
                'description' => 'Gugudde TV - Religious broadcasting',
                'sort_order' => 23,
                'votes' => 20,
                'urls' => [
                    ['url' => 'https://jk3lzqq4lw79-hls-live.5centscdn.com/gugudde/c9a1fdac6e082dd89e7173244f34d7b3.sdp/chunks.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => '5centsCDN', 'status' => 'Intermittent', 'notes' => 'May be geo-restricted'],
                ],
            ],
            [
                'name' => 'BTM TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'BTM TV Uganda',
                'sort_order' => 24,
                'votes' => 15,
                'urls' => [
                    ['url' => 'https://btmug.zerocdn.org/hls/stream.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ZeroCDN', 'status' => 'Intermittent', 'notes' => 'Not 24/7'],
                ],
            ],
            [
                'name' => 'Faraja Television',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Faraja Television Uganda',
                'sort_order' => 25,
                'votes' => 10,
                'urls' => [
                    ['url' => 'https://panel.freedomflixtv.org:3868/hybrid/play.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'Freedom Flix', 'status' => 'Intermittent', 'notes' => 'Not 24/7'],
                ],
            ],
            [
                'name' => 'ACW UG TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'ACW UG TV - General broadcasting',
                'sort_order' => 26,
                'votes' => 10,
                'urls' => [
                    ['url' => 'https://live.acwugtv.com/hls/stream.m3u8', 'format' => 'hls', 'quality' => 'SD', 'cdn_provider' => 'ACW Direct', 'status' => 'Intermittent'],
                ],
            ],

            // ── Section 3: Previously Working (historical, stored as inactive) ──
            [
                'name' => 'NBS Television',
                'type' => 'tv',
                'category' => 'News',
                'description' => 'NBS Television - Uganda\'s leading news channel',
                'logo_url' => 'https://i.imgur.com/NhBp70f.png',
                'sort_order' => 30,
                'votes' => 800,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/nbs/playlist.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive', 'notes' => 'Previously working Oct 2025, currently 403'],
                ],
            ],
            [
                'name' => 'NTV Uganda',
                'type' => 'tv',
                'category' => 'News',
                'description' => 'NTV Uganda - Nation Media Group news channel',
                'logo_url' => 'https://i.imgur.com/Ut3k1aA.png',
                'sort_order' => 31,
                'votes' => 900,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ntvuganda/playlist.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive', 'notes' => 'Previously working Oct 2025'],
                ],
            ],
            [
                'name' => 'UBC TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Uganda Broadcasting Corporation - State broadcaster',
                'logo_url' => 'https://i.imgur.com/KkWcEcz.png',
                'sort_order' => 32,
                'votes' => 600,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ubc/playlist.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Spark TV',
                'type' => 'tv',
                'category' => 'News',
                'description' => 'Spark TV - Nation Media Group entertainment/news',
                'logo_url' => 'https://i.imgur.com/W3LTb3p.png',
                'sort_order' => 33,
                'votes' => 500,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/sparktv/playlist.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'BBS TV',
                'type' => 'tv',
                'category' => 'General',
                'language' => 'Luganda',
                'description' => 'BBS TV - Buganda Broadcasting Service',
                'logo_url' => 'https://i.imgur.com/QCnfEQi.png',
                'sort_order' => 34,
                'votes' => 400,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bbstv/playlist.m3u8', 'format' => 'hls', 'quality' => 'HD', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'KBS TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'KBS TV Uganda',
                'sort_order' => 35,
                'votes' => 100,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/kbstv/playlist.m3u8', 'format' => 'hls', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Baba TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Baba TV Uganda',
                'sort_order' => 36,
                'votes' => 80,
                'urls' => [
                    ['url' => 'https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/babatv/playlist.m3u8', 'format' => 'hls', 'cdn_provider' => 'UVO TV / Fastly', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Ground TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Ground TV Uganda',
                'sort_order' => 37,
                'votes' => 50,
                'urls' => [
                    ['url' => 'https://stream.hydeinnovations.com/ground-tv/index.m3u8', 'format' => 'hls', 'cdn_provider' => 'Hyde Innovations', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Host TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Host TV Uganda',
                'sort_order' => 38,
                'votes' => 40,
                'urls' => [
                    ['url' => 'https://streamfi-hosttv1.zettawiseroutes.com:8181/hls/stream.m3u8', 'format' => 'hls', 'cdn_provider' => 'ZettaWise Routes', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Trust TV',
                'type' => 'tv',
                'category' => 'General',
                'description' => 'Trust TV Uganda',
                'sort_order' => 39,
                'votes' => 30,
                'urls' => [
                    ['url' => 'https://50de0c354d.tuhlprintltd.com/live/trust-tv/index.m3u8', 'format' => 'hls', 'cdn_provider' => 'Tuhlprint Ltd', 'status' => 'Inactive'],
                ],
            ],
            [
                'name' => 'Westnile TV',
                'type' => 'tv',
                'category' => 'General',
                'region' => 'West Nile',
                'description' => 'Westnile TV - Regional broadcasting',
                'sort_order' => 40,
                'votes' => 25,
                'urls' => [
                    ['url' => 'https://az-streamingserver.com:8443/live/westniletv/playlist.m3u8', 'format' => 'hls', 'cdn_provider' => 'AZ Streaming Server', 'status' => 'Inactive'],
                ],
            ],
        ];
    }

    private function getRadioStations(): array
    {
        return [
            // ── Major / Popular Stations ──
            [
                'name' => 'Akaboozi FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '87.9 FM',
                'region' => 'Kampala',
                'description' => 'Akaboozi FM 87.9 - Popular Luganda radio station',
                'language' => 'Luganda',
                'sort_order' => 1,
                'votes' => 16932,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://162.244.80.52:8732/stream.mp3', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'Direct IP'],
                    ['url' => 'http://162.244.80.52:8732/stream', 'label' => 'Alt', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'Direct IP'],
                ],
            ],
            [
                'name' => 'MCF Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'frequency' => '98.7 FM',
                'region' => 'Kampala',
                'description' => 'Mutundwe Christian Fellowship Radio',
                'sort_order' => 2,
                'votes' => 13296,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://streams.radio.co/s79fbbb432/listen', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'Radio.co'],
                ],
            ],
            [
                'name' => 'Capital FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '91.3 FM',
                'region' => 'Kampala',
                'description' => 'Capital FM 91.3 - Uganda\'s premier English radio station',
                'sort_order' => 3,
                'votes' => 10397,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://5229.cloudrad.io:8316/;', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'CloudRad.io'],
                    ['url' => 'https://capitalfm.cloudrad.io/stream', 'label' => 'Alt', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'CloudRad.io'],
                ],
            ],
            [
                'name' => 'Beat FM',
                'type' => 'radio',
                'category' => 'Music',
                'frequency' => '96.3 FM',
                'region' => 'Kampala',
                'description' => 'Beat FM 96.3 - Music and entertainment',
                'sort_order' => 4,
                'votes' => 5844,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://5230.cloudrad.io:8354/', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'CloudRad.io'],
                    ['url' => 'http://5230.cloudrad.io:8354/live', 'label' => 'Alt', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'CloudRad.io'],
                ],
            ],
            [
                'name' => 'Radio Maria Uganda',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Radio Maria Uganda - Catholic radio station',
                'sort_order' => 5,
                'votes' => 5249,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://dreamsiteradiocp.com:8052/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 48, 'cdn_provider' => 'DreamSite'],
                ],
            ],
            [
                'name' => 'Sanyu FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '88.2 FM',
                'region' => 'Kampala',
                'description' => 'Sanyu FM 88.2 - Uganda\'s oldest private radio station',
                'sort_order' => 6,
                'votes' => 3365,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://s44.myradiostream.com:8138/stream', 'format' => 'aac', 'quality' => 'Audio', 'bitrate' => 48, 'cdn_provider' => 'MyRadioStream'],
                ],
            ],
            [
                'name' => 'Radio One',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '90.0 FM',
                'region' => 'Kampala',
                'description' => 'Radio One 90.0 FM - News, talk and entertainment',
                'sort_order' => 7,
                'votes' => 2685,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://162.244.80.52:8740/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'Direct IP'],
                    ['url' => 'https://radioone.loftuganda.tech/stream', 'label' => 'Alt', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'LoftUganda'],
                ],
            ],
            [
                'name' => 'KIIS Uganda',
                'type' => 'radio',
                'category' => 'Music',
                'frequency' => '100.9 FM',
                'region' => 'Kampala',
                'description' => 'KIIS Uganda 100.9 FM - Contemporary hits',
                'sort_order' => 8,
                'votes' => 2051,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'http://14867.cloudrad.io:9224/live', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'CloudRad.io'],
                ],
            ],
            [
                'name' => 'Radio Pacis',
                'type' => 'radio',
                'category' => 'Religious',
                'frequency' => '90.9 FM',
                'region' => 'Arua',
                'description' => 'Radio Pacis 90.9 FM - Catholic Radio',
                'sort_order' => 9,
                'votes' => 974,
                'urls' => [
                    ['url' => 'https://radiopacisuganda.radioca.st/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'RadioCast'],
                ],
            ],
            [
                'name' => 'RX Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'RX Radio Uganda - Music and entertainment',
                'sort_order' => 10,
                'votes' => 1912,
                'urls' => [
                    ['url' => 'https://c14.radioboss.fm:18223/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'Gospel Radio East Africa',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Gospel Radio - Christian music and teaching',
                'sort_order' => 11,
                'votes' => 940,
                'urls' => [
                    ['url' => 'https://c32.radioboss.fm:18451/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'Christ FM',
                'type' => 'radio',
                'category' => 'Religious',
                'frequency' => '91.6 FM',
                'description' => 'Christ FM 91.6 - Christian radio station',
                'sort_order' => 12,
                'votes' => 814,
                'urls' => [
                    ['url' => 'http://5.135.154.69:15664/;', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'Direct IP'],
                    ['url' => 'http://s39.myradiostream.com/:15664/;', 'label' => 'Alt', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'MyRadioStream'],
                ],
            ],
            [
                'name' => 'Kaboozi FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '104.4 FM',
                'region' => 'Kampala',
                'language' => 'Luganda',
                'description' => 'Kaboozi FM 104.4 - Luganda entertainment radio',
                'sort_order' => 13,
                'votes' => 294,
                'urls' => [
                    ['url' => 'http://162.244.80.52:8732/;stream.mp3', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'Direct IP'],
                ],
            ],
            [
                'name' => 'Next Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '106.1 FM',
                'region' => 'Kampala',
                'description' => 'Next Radio 106.1 FM - News and entertainment',
                'sort_order' => 14,
                'votes' => 421,
                'urls' => [
                    ['url' => 'https://stream-154.zeno.fm/lbca7zintcnuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm', 'needs_token_refresh' => true],
                ],
            ],
            [
                'name' => 'Crooze FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Western Uganda',
                'description' => 'Crooze FM - Western Uganda radio',
                'sort_order' => 15,
                'votes' => 281,
                'urls' => [
                    ['url' => 'https://stream-159.zeno.fm/vyxwdk08apxtv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm', 'needs_token_refresh' => true],
                ],
            ],
            [
                'name' => 'Rock FM Uganda',
                'type' => 'radio',
                'category' => 'Music',
                'region' => 'Kampala',
                'description' => 'Rock FM - Rock and alternative music',
                'sort_order' => 16,
                'votes' => 182,
                'urls' => [
                    ['url' => 'http://titan.shoutca.st:8341/;', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'Shoutcast'],
                ],
            ],
            [
                'name' => 'Favour FM',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '104.1 FM',
                'region' => 'Gulu',
                'description' => 'Favour FM 104.1 - Gulu regional radio',
                'sort_order' => 17,
                'votes' => 149,
                'urls' => [
                    ['url' => 'http://us5new.listen2myradio.com:2199/listen.php?port=8138&type=ice&mount=stream', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Listen2MyRadio'],
                ],
            ],
            [
                'name' => 'East Africa Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'East Africa Radio - Regional broadcasting',
                'sort_order' => 18,
                'votes' => 105,
                'urls' => [
                    ['url' => 'https://eatv.radioca.st/;', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'RadioCast'],
                ],
            ],
            [
                'name' => 'Bible 24/7',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Bible 24/7 - 24-hour Bible teaching radio',
                'sort_order' => 19,
                'votes' => 98,
                'urls' => [
                    ['url' => 'http://c28.radioboss.fm:8335/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'Yofochm Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'Yofochm Radio Uganda',
                'sort_order' => 20,
                'votes' => 97,
                'urls' => [
                    ['url' => 'https://c13.radioboss.fm:18053/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'Kitintale Christian Fellowship',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Kitintale Christian Fellowship Radio',
                'sort_order' => 21,
                'votes' => 83,
                'urls' => [
                    ['url' => 'https://c24.radioboss.fm:18185/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'Bible Trivia',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Bible Trivia Radio - Bible quizzes and teaching',
                'sort_order' => 22,
                'votes' => 32,
                'urls' => [
                    ['url' => 'https://streamer.radio.co/se1aece429/listen', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 96, 'cdn_provider' => 'Radio.co'],
                ],
            ],
            [
                'name' => 'KFM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '93.3 FM',
                'region' => 'Kampala',
                'description' => 'KFM 93.3 - Monitor Publications radio',
                'sort_order' => 23,
                'votes' => 31,
                'urls' => [
                    ['url' => 'http://radio.kfm.co.ug:8000/stream', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Direct', 'needs_token_refresh' => true, 'notes' => 'May be intermittent'],
                ],
            ],
            [
                'name' => 'EJAZZ Xtra',
                'type' => 'radio',
                'category' => 'Music',
                'region' => 'Kampala',
                'description' => 'EJAZZ Xtra - Jazz and music radio',
                'sort_order' => 24,
                'votes' => 47,
                'urls' => [
                    ['url' => 'https://c32.radioboss.fm:18320/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],
            [
                'name' => 'EMC Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'EMC Radio Kampala',
                'sort_order' => 25,
                'votes' => 11,
                'urls' => [
                    ['url' => 'http://c22.radioboss.fm:18040/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'RadioBoss.fm'],
                ],
            ],

            // ── Zeno.fm Hosted Stations ──
            [
                'name' => 'Uganda DJs',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'Uganda DJs - DJ mixes and Ugandan music',
                'sort_order' => 30,
                'votes' => 5561,
                'is_featured' => true,
                'urls' => [
                    ['url' => 'https://stream.zeno.fm/muzrp86994zuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Busoga One FM',
                'type' => 'radio',
                'category' => 'Regional',
                'region' => 'Jinja',
                'description' => 'Busoga One FM - Jinja regional station',
                'sort_order' => 31,
                'votes' => 1526,
                'urls' => [
                    ['url' => 'https://stream.zeno.fm/xna2aad7gc9uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Street Deejays Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Mbarara',
                'description' => 'Street Deejays Radio - Mbarara',
                'sort_order' => 32,
                'votes' => 345,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/nbwdnxz7na0uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Cloud Radio Uganda',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'Cloud Radio Uganda',
                'sort_order' => 33,
                'votes' => 152,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/eq0vu571ekhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Heathafro FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'Heathafro FM - Afro music radio',
                'sort_order' => 34,
                'votes' => 150,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/rdf0qac95p8uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Nup Radio',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '91.4 FM',
                'description' => 'Nup Radio 91.4 FM',
                'sort_order' => 35,
                'votes' => 101,
                'urls' => [
                    ['url' => 'https://stream.zeno.fm/gxjhbloltwluv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Kiira FM',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '88.6 FM',
                'region' => 'Jinja',
                'description' => 'Kiira FM 88.6 - Jinja',
                'sort_order' => 36,
                'votes' => 93,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/iydttapi8rguv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Voice of Kyankwanzi',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '89.7 FM',
                'region' => 'Kiboga',
                'description' => 'Voice of Kyankwanzi 89.7 FM',
                'sort_order' => 37,
                'votes' => 76,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/eyzf4ddwqcmvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'SDA Missions Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'SDA Missions Radio - Seventh Day Adventist',
                'sort_order' => 38,
                'votes' => 59,
                'urls' => [
                    ['url' => 'https://stream-57.zeno.fm/mkkr2bcgkf9uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Heaven FM Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Heaven FM Radio - Gospel music and teaching',
                'sort_order' => 39,
                'votes' => 58,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/eequgfw72hhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Jubilee Radio',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '105.6 FM',
                'region' => 'Fort Portal',
                'description' => 'Jubilee Radio 105.6 FM - Fort Portal',
                'sort_order' => 40,
                'votes' => 73,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/f3y3up2k07zuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Kyoga Veritas Radio',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '91.5 FM',
                'region' => 'Soroti',
                'description' => 'Kyoga Veritas Radio 91.5 FM - Soroti',
                'sort_order' => 41,
                'votes' => 50,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/hyyzuphrsg0uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'SKYNET FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'SKYNET FM Uganda',
                'sort_order' => 42,
                'votes' => 49,
                'urls' => [
                    ['url' => 'https://stream-44.zeno.fm/1uhqawtfk5zuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Church Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Church Radio Uganda',
                'sort_order' => 43,
                'votes' => 48,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/k0weys53f78uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Good News Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Good News Radio - Gospel broadcasting',
                'sort_order' => 44,
                'votes' => 47,
                'urls' => [
                    ['url' => 'http://node-01.zeno.fm/km203bn6qnruv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Prayer Alter Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Prayer Alter Radio Uganda',
                'sort_order' => 45,
                'votes' => 47,
                'urls' => [
                    ['url' => 'https://node-33.zeno.fm/1gfmyttkephvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Voice Of Heaven',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Voice Of Heaven Radio',
                'sort_order' => 46,
                'votes' => 32,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/s961sfesdmntv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Christ Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Lira',
                'description' => 'Christ Radio - Lira',
                'sort_order' => 47,
                'votes' => 29,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/zupkzgrj4dauv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Prayer Tower Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Prayer Tower Radio Kampala',
                'sort_order' => 48,
                'votes' => 29,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/ymapb78yznhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Shalom Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Jinja',
                'description' => 'Shalom Radio - Jinja',
                'sort_order' => 49,
                'votes' => 27,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/rvnov6bmdhsvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Radio Yoo',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'Radio Yoo Uganda',
                'sort_order' => 50,
                'votes' => 25,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/v73tc5gwaphvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Dema Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Dema Radio - Gospel Promotions',
                'sort_order' => 51,
                'votes' => 24,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/m96foqqk7bxuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Exodus Comfort Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Mbarara',
                'description' => 'Exodus Comfort Radio - Mbarara',
                'sort_order' => 52,
                'votes' => 24,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/k2zma0qewtjvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Enjiri Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'Enjiri Radio Uganda',
                'sort_order' => 53,
                'votes' => 24,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/xdb8nazajqcvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Nakawa Online Radio',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'Nakawa Online Radio - Kampala',
                'sort_order' => 54,
                'votes' => 23,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/6hs5suuvqfhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Chosen Radio Uganda',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Chosen Radio Uganda',
                'sort_order' => 55,
                'votes' => 22,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/6uxwuag3srhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Gospel Kingz',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Gospel Kingz Radio',
                'sort_order' => 56,
                'votes' => 22,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/vstzctms6rhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Radio Sinza',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'Radio Sinza Uganda',
                'sort_order' => 57,
                'votes' => 21,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/raoopfak6k2vv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Way To God Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Way To God Radio Uganda',
                'sort_order' => 58,
                'votes' => 21,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/g876nxxz8vzuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Turn Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Turn Radio - Revive Your Soul',
                'sort_order' => 59,
                'votes' => 19,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/cuejngegi8btv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'UgOnlineMedia',
                'type' => 'radio',
                'category' => 'Entertainment',
                'region' => 'Kampala',
                'description' => 'UgOnlineMedia Radio',
                'sort_order' => 60,
                'votes' => 18,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/8t4dtkxfgkuuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Christ Love Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Christ Love Radio Uganda',
                'sort_order' => 61,
                'votes' => 16,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/orioba9siustv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Glory FM Maganjo',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Glory FM - Maganjo, Kampala',
                'sort_order' => 62,
                'votes' => 16,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/bn7dbg8w0nhvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Sanctuary FM',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Sanctuary FM Kampala',
                'sort_order' => 63,
                'votes' => 14,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/vyx334hsbphvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Heavenly Altar Church Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'region' => 'Kampala',
                'description' => 'Heavenly Altar Church Radio',
                'sort_order' => 64,
                'votes' => 13,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/6s8719ctbphvv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Buyinza FM',
                'type' => 'radio',
                'category' => 'Regional',
                'description' => 'Buyinza FM Uganda',
                'sort_order' => 65,
                'votes' => 13,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/wcancipcbrevv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Teshuvah Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Teshuvah Radio Uganda',
                'sort_order' => 66,
                'votes' => 4,
                'urls' => [
                    ['url' => 'https://stream.zeno.fm/3qpkku63z5quv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Promise Radio UG',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Promise Radio Uganda',
                'sort_order' => 67,
                'votes' => 3,
                'urls' => [
                    ['url' => 'http://stream.zeno.fm/hkzgeqlcjoxuv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'Zeno.fm'],
                ],
            ],
            [
                'name' => 'Bible Indepth Radio',
                'type' => 'radio',
                'category' => 'Religious',
                'description' => 'Bible Indepth Radio - Deep Bible study',
                'sort_order' => 68,
                'votes' => 274,
                'urls' => [
                    ['url' => 'https://stream.radiojar.com/n6c576nrga0uv', 'format' => 'mp3', 'quality' => 'Audio', 'cdn_provider' => 'RadioJar', 'needs_token_refresh' => true],
                ],
            ],
            [
                'name' => 'My Radio Uganda',
                'type' => 'radio',
                'category' => 'Entertainment',
                'description' => 'My Radio Uganda',
                'sort_order' => 69,
                'votes' => 0,
                'urls' => [
                    ['url' => 'http://myradioug.duckdns.org:8000/radio.mp3', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 192, 'cdn_provider' => 'Direct'],
                ],
            ],

            // ── Currently Offline (stored for future use) ──
            [
                'name' => 'Radio Buddu',
                'type' => 'radio',
                'category' => 'Regional',
                'frequency' => '95.5 FM',
                'description' => 'Radio Buddu 95.5 FM',
                'sort_order' => 80,
                'votes' => 2808,
                'urls' => [
                    ['url' => 'https://dc4.serverse.com/proxy/ccmxrgub/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 64, 'cdn_provider' => 'Serverse', 'status' => 'Inactive', 'notes' => 'Currently offline'],
                ],
            ],
            [
                'name' => 'Pearl FM',
                'type' => 'radio',
                'category' => 'Entertainment',
                'frequency' => '107.9 FM',
                'region' => 'Kampala',
                'description' => 'Pearl FM 107.9 - Entertainment radio',
                'sort_order' => 81,
                'votes' => 631,
                'urls' => [
                    ['url' => 'https://dc4.serverse.com/proxy/pearlfm/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 96, 'cdn_provider' => 'Serverse', 'status' => 'Inactive', 'notes' => 'Currently offline'],
                ],
            ],
            [
                'name' => 'NRG Uganda',
                'type' => 'radio',
                'category' => 'Music',
                'frequency' => '106.5 FM',
                'description' => 'NRG Uganda 106.5 FM',
                'sort_order' => 82,
                'votes' => 291,
                'urls' => [
                    ['url' => 'https://dc4.serverse.com/proxy/nrgugstream/stream', 'format' => 'mp3', 'quality' => 'Audio', 'bitrate' => 128, 'cdn_provider' => 'Serverse', 'status' => 'Inactive'],
                ],
            ],
        ];
    }
}
