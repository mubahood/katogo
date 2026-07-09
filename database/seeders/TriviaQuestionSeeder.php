<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TriviaQuestionSeeder extends Seeder
{
    /**
     * Seed trivia questions for the Movie Trivia game module.
     * Safe to run multiple times — uses upsert on id.
     */
    public function run(): void
    {
        $this->command->info('🎬 Seeding trivia questions...');

        $questions = $this->allQuestions();
        $chunks = array_chunk($questions, 100);

        $total = 0;
        foreach ($chunks as $chunk) {
            DB::table('trivia_questions')->upsert(
                $chunk,
                ['id'],
                ['question', 'difficulty', 'category', 'format', 'correct_answer', 'wrong_answers', 'hint', 'points', 'timer_seconds', 'version', 'status']
            );
            $total += count($chunk);
        }

        // Set version meta
        DB::table('trivia_meta')->updateOrInsert(
            ['key' => 'current_version'],
            ['value' => '1']
        );
        DB::table('trivia_meta')->updateOrInsert(
            ['key' => 'total_count'],
            ['value' => (string) $total]
        );

        $this->command->info("✅ Seeded {$total} trivia questions.");
    }

    private function q(int $id, string $diff, string $cat, string $question, string $correct, array $wrong, ?string $hint = null): array
    {
        $points = ['easy' => 10, 'medium' => 20, 'hard' => 30, 'expert' => 50, 'legendary' => 100];
        $timers = ['easy' => 15, 'medium' => 12, 'hard' => 10, 'expert' => 8, 'legendary' => 6];

        return [
            'id' => $id,
            'question' => $question,
            'difficulty' => $diff,
            'category' => $cat,
            'format' => 'multiple_choice',
            'correct_answer' => $correct,
            'wrong_answers' => json_encode($wrong),
            'hint' => $hint,
            'image_url' => null,
            'points' => $points[$diff] ?? 10,
            'timer_seconds' => $timers[$diff] ?? 15,
            'version' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function allQuestions(): array
    {
        // The Flutter app bundles all 2080 questions locally.
        // This seeder includes a representative subset for the API.
        // For the full set, import from the Flutter data files or use the admin panel.
        //
        // Categories: action_adventure, scifi_fantasy, drama_romance, comedy_animation,
        //             horror_thriller, classics_awards, directors_actors, music_quotes
        //
        // To populate the full dataset, run:
        //   php artisan trivia:import-from-flutter
        // or use the admin CSV import.

        return array_merge(
            $this->actionAdventure(),
            $this->scifiFantasy(),
            $this->dramaRomance(),
            $this->comedyAnimation(),
            $this->horrorThriller(),
            $this->classicsAwards(),
            $this->directorsActors(),
            $this->musicQuotes(),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTION & ADVENTURE (sample: 20 per difficulty = 100)
    // ═══════════════════════════════════════════════════════════════════════
    private function actionAdventure(): array
    {
        $c = 'action_adventure';
        return [
            // Easy
            $this->q(1001, 'easy', $c, 'What is the name of Indiana Jones\' weapon of choice?', 'A bullwhip', ['A sword', 'A boomerang', 'A crossbow']),
            $this->q(1002, 'easy', $c, 'In "The Dark Knight", who plays the Joker?', 'Heath Ledger', ['Jack Nicholson', 'Joaquin Phoenix', 'Jared Leto']),
            $this->q(1003, 'easy', $c, 'What metal are Wolverine\'s claws coated with?', 'Adamantium', ['Vibranium', 'Titanium', 'Kryptonite']),
            $this->q(1004, 'easy', $c, 'Which 1994 film stars Keanu Reeves on a bus that can\'t slow down?', 'Speed', ['Point Break', 'The Matrix', 'John Wick']),
            $this->q(1005, 'easy', $c, 'Who directed "Jurassic Park" (1993)?', 'Steven Spielberg', ['James Cameron', 'Ridley Scott', 'George Lucas']),
            $this->q(1006, 'easy', $c, 'In "Die Hard", what building do terrorists take over?', 'Nakatomi Plaza', ['Empire State Building', 'Willis Tower', 'One World Trade Center']),
            $this->q(1007, 'easy', $c, 'What is Thor\'s hammer called in the MCU?', 'Mjolnir', ['Stormbreaker', 'Gungnir', 'Hofund']),
            $this->q(1008, 'easy', $c, 'In "The Lord of the Rings", what must be destroyed in Mount Doom?', 'The One Ring', ['The Arkenstone', 'The Palantír', 'The Silmaril']),
            $this->q(1009, 'easy', $c, 'Who plays Ethan Hunt in the "Mission: Impossible" series?', 'Tom Cruise', ['Matt Damon', 'Daniel Craig', 'Chris Pine']),
            $this->q(1010, 'easy', $c, 'In "Pirates of the Caribbean", what is Captain Jack Sparrow\'s ship called?', 'The Black Pearl', ['The Flying Dutchman', 'The Queen Anne\'s Revenge', 'The Interceptor']),
            // Medium
            $this->q(1101, 'medium', $c, 'In "Mad Max: Fury Road", who plays Imperator Furiosa?', 'Charlize Theron', ['Gal Gadot', 'Scarlett Johansson', 'Emily Blunt']),
            $this->q(1102, 'medium', $c, 'What year was the original "Die Hard" released?', '1988', ['1986', '1990', '1985']),
            $this->q(1103, 'medium', $c, 'In "Kill Bill", what is the name of the assassination squad?', 'Deadly Viper Assassination Squad', ['Black Mamba Unit', 'Shadow Killers', 'Death Corps']),
            $this->q(1104, 'medium', $c, 'What gemstone is the MacGuffin in "Indiana Jones and the Temple of Doom"?', 'Sankara Stones', ['The Ark of the Covenant', 'The Holy Grail', 'A Crystal Skull']),
            $this->q(1105, 'medium', $c, 'Who directed "The Raid: Redemption"?', 'Gareth Evans', ['Park Chan-wook', 'Takashi Miike', 'John Woo']),
            $this->q(1106, 'medium', $c, 'In "Gladiator", what is Maximus\'s full name?', 'Maximus Decimus Meridius', ['Maximus Aurelius', 'Maximus Octavius', 'Maximus Tiberius']),
            $this->q(1107, 'medium', $c, 'What real event inspired "Black Hawk Down"?', 'Battle of Mogadishu (1993)', ['Battle of Fallujah', 'Battle of Khe Sanh', 'Battle of Tora Bora']),
            $this->q(1108, 'medium', $c, 'In "John Wick", what type of currency do assassins use?', 'Gold coins', ['Diamonds', 'Bitcoin', 'Blood money']),
            $this->q(1109, 'medium', $c, 'Who played the villain in "Skyfall"?', 'Javier Bardem (Silva)', ['Mads Mikkelsen', 'Christoph Waltz', 'Ralph Fiennes']),
            $this->q(1110, 'medium', $c, 'What martial art does Bruce Lee use in "Enter the Dragon"?', 'Jeet Kune Do', ['Kung Fu', 'Karate', 'Wing Chun']),
            // Hard
            $this->q(1201, 'hard', $c, 'In "Apocalypse Now", what opera music plays during the helicopter attack?', '"Ride of the Valkyries" by Richard Wagner', ['"1812 Overture"', '"O Fortuna"', '"Flight of the Bumblebee"']),
            $this->q(1202, 'hard', $c, 'What city does "The French Connection" take place in?', 'New York City', ['Paris', 'Marseille', 'Chicago']),
            $this->q(1203, 'hard', $c, 'Who choreographed the action sequences in "The Matrix"?', 'Yuen Woo-ping', ['Jet Li', 'Jackie Chan', 'Donnie Yen']),
            $this->q(1204, 'hard', $c, 'In "Crouching Tiger, Hidden Dragon", what weapon is stolen?', 'Green Destiny sword', ['Jade Dragon', 'Phoenix Blade', 'Tiger\'s Fang']),
            $this->q(1205, 'hard', $c, 'What Bond film features the "Golden Gun"?', 'The Man with the Golden Gun', ['Goldfinger', 'GoldenEye', 'A View to a Kill']),
            $this->q(1206, 'hard', $c, 'In "Heat", what event inspired the bank robbery shootout?', 'The North Hollywood shootout (though the film predated it by 2 years — it inspired the robbers)', ['A real Chicago heist', 'A training exercise', 'A documentary']),
            $this->q(1207, 'hard', $c, 'Who directed the original "Oldboy" (2003)?', 'Park Chan-wook', ['Bong Joon-ho', 'Kim Jee-woon', 'Lee Chang-dong']),
            $this->q(1208, 'hard', $c, 'What is the Bride\'s real name in "Kill Bill"?', 'Beatrix Kiddo', ['Victoria Fox', 'Mia Wallace', 'Elle Driver']),
            $this->q(1209, 'hard', $c, 'In "Casino Royale" (2006), what card game does Bond play?', 'Texas Hold\'em Poker', ['Baccarat', 'Blackjack', 'Roulette']),
            $this->q(1210, 'hard', $c, 'What was Bruce Lee\'s last completed film?', 'Enter the Dragon', ['Fist of Fury', 'Game of Death', 'The Way of the Dragon']),
            // Expert
            $this->q(1301, 'expert', $c, 'In "Bullitt" (1968), through which San Francisco streets was the famous car chase filmed?', 'Taylor, Filbert, and other Potrero Hill streets', ['Golden Gate Bridge', 'Market Street', 'Lombard Street only']),
            $this->q(1302, 'expert', $c, 'What was the $40 million-over-budget 1995 action disaster that nearly sank a studio?', 'Waterworld', ['Cutthroat Island', 'Congo', 'Judge Dredd']),
            $this->q(1303, 'expert', $c, 'Who invented the "Bullet Time" effect seen in "The Matrix"?', 'John Gaeta', ['The Wachowskis', 'James Cameron', 'Phil Tippett']),
            $this->q(1304, 'expert', $c, 'In "Seven Samurai", how many samurai survive the final battle?', 'Three', ['Four', 'Two', 'One']),
            $this->q(1305, 'expert', $c, 'What technique did George Miller use to keep the audience\'s eyes centered in "Fury Road"?', 'Center-framing — keeping the point of interest always at the center of the frame', ['Dutch angles', 'Split screens', 'Fast zooms']),
            $this->q(1306, 'expert', $c, 'What was Jackie Chan\'s first Hollywood film?', 'The Big Brawl (Battle Creek Brawl, 1980)', ['Rumble in the Bronx', 'Rush Hour', 'Shanghai Noon']),
            $this->q(1307, 'expert', $c, 'In "Hard Boiled", how long is the famous hospital single-take shootout?', 'Nearly 3 minutes (2 minutes 42 seconds)', ['30 seconds', '10 minutes', '45 seconds']),
            $this->q(1308, 'expert', $c, 'What unique production challenge did "Apocalypse Now" face in the Philippines?', 'Typhoon Olga destroyed sets, Martin Sheen had a heart attack, and Marlon Brando arrived unprepared and overweight', ['Ran out of film', 'Lost the script', 'Crew strike']),
            $this->q(1309, 'expert', $c, 'In "The Raid", what Indonesian martial art is featured?', 'Pencak Silat', ['Muay Thai', 'Krav Maga', 'Hapkido']),
            $this->q(1310, 'expert', $c, 'What specific stunt did Keanu Reeves perform in "Point Break" without a double?', 'The skydiving scenes (he did over 50 jumps)', ['Car chase', 'Surfing', 'Building jump']),
            // Legendary
            $this->q(1401, 'legendary', $c, 'What was Akira Kurosawa\'s wipe-transition technique and which Hollywood director adopted it?', 'Kurosawa used "wipe cuts" extensively; George Lucas used them in Star Wars as homage', ['Fade outs', 'Jump cuts', 'Dissolves']),
            $this->q(1402, 'legendary', $c, 'How did the chariot race in "Ben-Hur" (1959) push filmmaking boundaries?', 'Used 15,000 extras, 18 chariots in the largest practical set ever built (18 acres), took 5 weeks to film', ['All CGI', '100 extras', 'One day of filming']),
            $this->q(1403, 'legendary', $c, 'What was the first film to use a Steadicam?', 'Bound for Glory (1976), then famously Rocky (1976)', ['The Shining', 'Goodfellas', 'Star Wars']),
            $this->q(1404, 'legendary', $c, 'In "The Good, the Bad and the Ugly", what was the unusual production approach?', 'Ennio Morricone composed the music BEFORE filming and Sergio Leone directed scenes to match the score', ['No music', 'All improv', 'Shot on video']),
            $this->q(1405, 'legendary', $c, 'What specific camera innovation did James Cameron develop for "The Abyss"?', 'VistaGlide deepwater camera system for underwater photography', ['Motion capture', '3D cameras', 'Drone cameras']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCI-FI & FANTASY
    // ═══════════════════════════════════════════════════════════════════════
    private function scifiFantasy(): array
    {
        $c = 'scifi_fantasy';
        return [
            $this->q(2001, 'easy', $c, 'In "The Matrix", what color pill does Neo take?', 'Red', ['Blue', 'Green', 'White']),
            $this->q(2002, 'easy', $c, 'What is the name of the spaceship in "Alien"?', 'USCSS Nostromo', ['Millennium Falcon', 'Enterprise', 'Serenity']),
            $this->q(2003, 'easy', $c, 'In "Star Wars", what is the weapon of a Jedi Knight?', 'Lightsaber', ['Blaster', 'Phaser', 'Photon torpedo']),
            $this->q(2004, 'easy', $c, 'Who plays Gandalf in "The Lord of the Rings"?', 'Ian McKellen', ['Patrick Stewart', 'Christopher Lee', 'Michael Gambon']),
            $this->q(2005, 'easy', $c, 'What planet is Superman from?', 'Krypton', ['Mars', 'Asgard', 'Gallifrey']),
            $this->q(2101, 'medium', $c, 'What year does "Back to the Future" travel to?', '1955 (and 2015, and 1885)', ['1965', '1945', '1975']),
            $this->q(2102, 'medium', $c, 'In "Blade Runner", what are the bio-engineered beings called?', 'Replicants', ['Androids', 'Synthetics', 'Cylons']),
            $this->q(2103, 'medium', $c, 'What was the first Pixar feature film?', 'Toy Story (1995)', ['A Bug\'s Life', 'Finding Nemo', 'Monsters, Inc.']),
            $this->q(2104, 'medium', $c, 'In "Inception", what totem does Cobb use?', 'A spinning top', ['A loaded die', 'A chess piece', 'A poker chip']),
            $this->q(2105, 'medium', $c, 'Who directed "Arrival" (2016)?', 'Denis Villeneuve', ['Christopher Nolan', 'Ridley Scott', 'Alex Garland']),
            $this->q(2201, 'hard', $c, 'In "2001: A Space Odyssey", what is the AI computer called?', 'HAL 9000', ['WOPR', 'Skynet', 'GERTY']),
            $this->q(2202, 'hard', $c, 'What Philip K. Dick story is "Blade Runner" based on?', '"Do Androids Dream of Electric Sheep?"', ['"The Minority Report"', '"A Scanner Darkly"', '"Ubik"']),
            $this->q(2203, 'hard', $c, 'In "Dune", what is the "spice" also known as?', 'Melange', ['Shai-Hulud', 'Heighliner', 'Bene Gesserit']),
            $this->q(2204, 'hard', $c, 'What Stanislaw Lem novel was "Solaris" adapted from?', '"Solaris" (1961)', ['"The Invincible"', '"Return from the Stars"', '"Eden"']),
            $this->q(2205, 'hard', $c, 'In "The Matrix", what is the name of the last human city?', 'Zion', ['Neo York', 'Achilles', 'Morpheus']),
            $this->q(2301, 'expert', $c, 'What practical effects technique was used for the Xenomorph in "Alien"?', 'A 7-foot-tall (Bolaji Badejo) actor in a suit designed by H.R. Giger', ['Full CGI', 'Puppet only', 'Stop motion']),
            $this->q(2302, 'expert', $c, 'What was the significance of "Silent Running" (1972) for VFX?', 'Early use of drones controlled by amputee actors and ecological sci-fi before Star Wars', ['First CGI', 'First 3D', 'First IMAX']),
            $this->q(2303, 'expert', $c, 'In "Stalker" (1979) by Tarkovsky, what is "The Zone"?', 'A mysterious restricted area with a room that fulfills innermost desires', ['A prison', 'A city', 'A planet']),
            $this->q(2304, 'expert', $c, 'What specific camera trick did Kubrick use in "2001" for the rotating spaceship?', 'A giant 30-ton rotating set (centrifuge) that actors walked inside', ['Green screen', 'Wire work', 'Miniatures only']),
            $this->q(2305, 'expert', $c, 'What was the first film to use CGI for a main character?', '"Young Sherlock Holmes" (1985) — stained glass knight; "Tron" (1982) for environments', ['Toy Story', 'Jurassic Park', 'The Abyss']),
            $this->q(2401, 'legendary', $c, 'What was the "slit-scan" technique used in "2001: A Space Odyssey"?', 'Long exposures with artwork moved past a slit to create the Stargate sequence', ['A normal camera move', 'CGI effect', 'A painting']),
            $this->q(2402, 'legendary', $c, 'What specific camera system did James Cameron invent for "Avatar"?', 'The virtual camera (Simul-cam) — allowing real-time viewing of actors in the CG world', ['A normal camera', 'A drone', 'A periscope']),
            $this->q(2403, 'legendary', $c, 'In "Solaris" (1972 Tarkovsky), what was the levitation scene and how was it done?', 'Zero-gravity scene achieved by a fighter jet doing parabolic arcs', ['Wire work', 'CGI', 'Underwater']),
            $this->q(2404, 'legendary', $c, 'What was "Metropolis" (1927) and its significance for sci-fi cinema?', 'Fritz Lang\'s dystopian film — first feature-length sci-fi, pioneered Schüfftan process for visual effects', ['A short film', 'A comedy', 'A documentary']),
            $this->q(2405, 'legendary', $c, 'What specific technique did Denis Villeneuve use for the heptapod language in "Arrival"?', 'Artist Martine Bertrand created sumi-e ink circle designs, composited with VFX as "logograms"', ['CGI letters', 'Real squid ink', 'Hand-drawn on set']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DRAMA & ROMANCE
    // ═══════════════════════════════════════════════════════════════════════
    private function dramaRomance(): array
    {
        $c = 'drama_romance';
        return [
            $this->q(3001, 'easy', $c, 'In "Titanic", who plays Jack Dawson?', 'Leonardo DiCaprio', ['Brad Pitt', 'Tom Hanks', 'Matt Damon']),
            $this->q(3002, 'easy', $c, 'What 1994 film follows a man sitting on a bench telling his life story?', 'Forrest Gump', ['Cast Away', 'The Green Mile', 'Big']),
            $this->q(3003, 'easy', $c, 'In "The Shawshank Redemption", what does Andy crawl through to escape?', 'A sewage pipe', ['A tunnel', 'A ventilation shaft', 'A laundry chute']),
            $this->q(3004, 'easy', $c, 'Who plays the lead in "The Pursuit of Happyness"?', 'Will Smith', ['Denzel Washington', 'Jamie Foxx', 'Morgan Freeman']),
            $this->q(3005, 'easy', $c, 'In "The Notebook", who wrote the original novel?', 'Nicholas Sparks', ['John Green', 'Stephen King', 'Nora Roberts']),
            $this->q(3101, 'medium', $c, 'In "Schindler\'s List", how many Jews did Oskar Schindler save?', 'Approximately 1,200', ['100', '500', '3,000']),
            $this->q(3102, 'medium', $c, 'What language is "Parasite" (2019) primarily in?', 'Korean', ['Japanese', 'Mandarin', 'Thai']),
            $this->q(3103, 'medium', $c, 'In "Good Will Hunting", where does Will work at the beginning?', 'As a janitor at MIT', ['As a teacher', 'As a programmer', 'As a bartender']),
            $this->q(3104, 'medium', $c, 'Who directed "12 Years a Slave"?', 'Steve McQueen', ['Spike Lee', 'Barry Jenkins', 'Ava DuVernay']),
            $this->q(3105, 'medium', $c, 'In "A Beautiful Mind", what condition does John Nash have?', 'Paranoid schizophrenia', ['Bipolar disorder', 'Autism', 'Dementia']),
            $this->q(3201, 'hard', $c, 'In "Moonlight" (2016), what are the three chapters named?', 'i. Little, ii. Chiron, iii. Black', ['Past, Present, Future', 'Boy, Teen, Man', 'Dawn, Noon, Night']),
            $this->q(3202, 'hard', $c, 'What was the real profession of the man "The Pianist" is based on?', 'Władysław Szpilman — a Polish-Jewish pianist and composer', ['A violinist', 'A singer', 'A conductor']),
            $this->q(3203, 'hard', $c, 'In "Eternal Sunshine of the Spotless Mind", what company erases memories?', 'Lacuna, Inc.', ['Forget Me Not', 'MindWipe', 'Neuralink']),
            $this->q(3204, 'hard', $c, 'Who directed "Amour" (2012)?', 'Michael Haneke', ['Pedro Almodóvar', 'François Ozon', 'Jean-Luc Godard']),
            $this->q(3205, 'hard', $c, 'In "Manchester by the Sea", what tragedy haunts the protagonist?', 'An accidental house fire killed his three children', ['A car accident', 'A drowning', 'A war injury']),
            $this->q(3301, 'expert', $c, 'What Iranian film won the first-ever Foreign Language Oscar for Iran?', '"A Separation" (2011) by Asghar Farhadi', ['The Salesman', 'Taste of Cherry', 'Close-Up']),
            $this->q(3302, 'expert', $c, 'In "In the Mood for Love", what color is the cheongsam dress most associated with Mrs. Chan?', 'Multiple — she wears over 20 different cheongsams; the red floral is most iconic', ['Just blue', 'Only white', 'Always black']),
            $this->q(3303, 'expert', $c, 'What technique did Terrence Malick use in "The Tree of Life"?', 'Non-linear narrative with whispered voiceovers, natural light, and cosmic imagery (including real macro footage)', ['Standard editing', 'All CGI', 'Found footage']),
            $this->q(3304, 'expert', $c, 'Who was the real Erin Brockovich and what did she expose?', 'A legal clerk who built a case against PG&E for chromium-6 contamination of groundwater in Hinkley, CA', ['An oil spill', 'A pharmaceutical company', 'A tobacco company']),
            $this->q(3305, 'expert', $c, 'In "Requiem for a Dream", what technique does Darren Aronofsky use for drug sequences?', 'A "hip hop montage" — rapid split-screen editing with extreme close-ups and sound design', ['Slow motion', 'Black and white', 'No special effects']),
            $this->q(3401, 'legendary', $c, 'What was the Dogme 95 movement and which film best exemplifies it?', 'A Danish filmmaking manifesto by Lars von Trier and Thomas Vinterberg — "Festen" (1998) followed the "Vow of Chastity" rules', ['A Hollywood genre', 'A camera brand', 'An acting school']),
            $this->q(3402, 'legendary', $c, 'What was the Italian Neorealist movement and its most important film?', 'Post-WWII movement using non-professional actors, location shooting, social themes — "Bicycle Thieves" (1948) by De Sica', ['CGI movement', 'A music genre', 'A painting style']),
            $this->q(3403, 'legendary', $c, 'In "Tokyo Story" (1953), what filming technique defines Ozu\'s style?', 'Low camera position (tatami shot), minimal camera movement, 360-degree shooting space', ['Shaky cam', 'Aerial shots', 'Fast cutting']),
            $this->q(3404, 'legendary', $c, 'What technique did Wong Kar-wai use in "In the Mood for Love"?', 'Step-printing (overcranking) for slow motion, narrow framing to show characters never touching, no wide shots', ['Fast editing', 'CGI', 'Standard filming']),
            $this->q(3405, 'legendary', $c, 'What was the Nouvelle Vague and which film launched it?', 'French New Wave — "Breathless" (1960) by Jean-Luc Godard with jump cuts, handheld camera, improvised dialogue', ['An American trend', 'A music genre', 'A food movement']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMEDY & ANIMATION
    // ═══════════════════════════════════════════════════════════════════════
    private function comedyAnimation(): array
    {
        $c = 'comedy_animation';
        return [
            $this->q(4001, 'easy', $c, 'In "Shrek", what type of creature is the title character?', 'An ogre', ['A troll', 'A goblin', 'A giant']),
            $this->q(4002, 'easy', $c, 'What Pixar film features a rat who dreams of being a chef?', 'Ratatouille', ['Finding Nemo', 'The Incredibles', 'Cars']),
            $this->q(4003, 'easy', $c, 'Who voices Woody in "Toy Story"?', 'Tom Hanks', ['Tim Allen', 'Robin Williams', 'Billy Crystal']),
            $this->q(4004, 'easy', $c, 'In "The Hangover", what city does the bachelor party take place in?', 'Las Vegas', ['Miami', 'New York', 'Atlantic City']),
            $this->q(4005, 'easy', $c, 'What Disney film features a magic carpet ride?', 'Aladdin', ['Mulan', 'Tangled', 'Brave']),
            $this->q(4101, 'medium', $c, 'In "Groundhog Day", what song plays every morning on the alarm?', '"I Got You Babe" by Sonny & Cher', ['Happy Together', 'Good Morning', 'Here Comes the Sun']),
            $this->q(4102, 'medium', $c, 'Who directed "The Grand Budapest Hotel"?', 'Wes Anderson', ['Edgar Wright', 'Taika Waititi', 'Noah Baumbach']),
            $this->q(4103, 'medium', $c, 'In "Up", what is the name of the floating house\'s destination waterfall?', 'Paradise Falls (inspired by Angel Falls)', ['Niagara Falls', 'Victoria Falls', 'Rainbow Falls']),
            $this->q(4104, 'medium', $c, 'What year was "Spirited Away" released?', '2001', ['2003', '1999', '2005']),
            $this->q(4105, 'medium', $c, 'In "Bridesmaids", who plays the lead character Annie?', 'Kristen Wiig', ['Melissa McCarthy', 'Maya Rudolph', 'Amy Poehler']),
            $this->q(4201, 'hard', $c, 'What animation studio created "Akira"?', 'Tokyo Movie Shinsha (TMS Entertainment)', ['Studio Ghibli', 'Madhouse', 'Toei Animation']),
            $this->q(4202, 'hard', $c, 'In "Dr. Strangelove", what is Slim Pickens\' character\'s name?', 'Major T.J. "King" Kong', ['General Ripper', 'Colonel Bat Guano', 'President Muffley']),
            $this->q(4203, 'hard', $c, 'What technique makes "Spider-Man: Into the Spider-Verse" unique?', 'Combination of CGI animation with hand-drawn comic book elements (halftone dots, Kirby Krackle, smear frames)', ['Traditional hand-drawn only', 'Live action compositing', 'Stop motion']),
            $this->q(4204, 'hard', $c, 'Who directed "This Is Spinal Tap"?', 'Rob Reiner', ['Christopher Guest', 'Mel Brooks', 'Woody Allen']),
            $this->q(4205, 'hard', $c, 'In "WALL-E", what corporation caused Earth\'s environmental collapse?', 'Buy-n-Large (BnL)', ['Globocorp', 'MegaCo', 'OmniConsumer Products']),
            $this->q(4301, 'expert', $c, 'What animation technique did "Loving Vincent" use?', '65,000 oil paintings in the style of Van Gogh — each frame hand-painted by 125 artists', ['Normal CGI', 'Pencil animation', 'Claymation']),
            $this->q(4302, 'expert', $c, 'What specific comedy technique does Edgar Wright\'s "Cornetto Trilogy" use?', 'Visual comedy through editing — transitions that act as jokes, whip pans, and match cuts for comedic timing', ['Only dialogue comedy', 'Slapstick only', 'Improvisation']),
            $this->q(4303, 'expert', $c, 'In "Persepolis", what real historical events form the backdrop?', 'The Iranian Revolution (1979) and the Iran-Iraq War — based on Marjane Satrapi\'s autobiographical graphic novel', ['WWII', 'American Revolution', 'French Revolution']),
            $this->q(4304, 'expert', $c, 'What was Hayao Miyazaki\'s first film as director?', '"Lupin III: The Castle of Cagliostro" (1979) — his first feature; "Nausicaä" (1984) was his first Studio Ghibli-associated film', ['Spirited Away', 'My Neighbor Totoro', 'Princess Mononoke']),
            $this->q(4305, 'expert', $c, 'What technique does Aardman Animations use for Wallace & Gromit?', 'Claymation (plasticine clay stop-motion) at 25 frames per second — each second requires 25 individual adjustments', ['CGI', 'Hand-drawn', 'Puppetry']),
            $this->q(4401, 'legendary', $c, 'What was the "multiplane camera" and who invented it for animation?', 'Walt Disney\'s team developed it — multiple layers of artwork at different distances from the camera to create parallax depth, first used in "The Old Mill" (1937)', ['A regular camera', 'A projector', 'A drawing tool']),
            $this->q(4402, 'legendary', $c, 'What specific technology did "Toy Story" (1995) pioneer?', 'First feature-length film entirely rendered in CGI — using Pixar\'s RenderMan software, taking 800,000 machine hours', ['Hand-drawn', 'Claymation', 'Live action']),
            $this->q(4403, 'legendary', $c, 'What was Winsor McCay\'s "Gertie the Dinosaur" (1914) and its significance?', 'One of the earliest animated films — McCay drew 10,000 frames by hand and performed live alongside the projected animation', ['A CGI film', 'A live-action film', 'A painting']),
            $this->q(4404, 'legendary', $c, 'What unique challenge did "The Nightmare Before Christmas" production face?', 'Over 100,000 frames shot over 3 years; each character had multiple interchangeable heads (Jack had over 400)', ['It was easy', 'One week shoot', 'All CGI']),
            $this->q(4405, 'legendary', $c, 'What was the "Ub Iwerks" technique and its contribution to early Disney?', 'Ub Iwerks was the key animator who drew Mickey Mouse and animated "Steamboat Willie" — he drew up to 700 frames per day', ['A sound technique', 'A camera', 'A lighting method']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HORROR & THRILLER
    // ═══════════════════════════════════════════════════════════════════════
    private function horrorThriller(): array
    {
        $c = 'horror_thriller';
        return [
            $this->q(5001, 'easy', $c, 'In "The Shining", what phrase does Jack type repeatedly?', '"All work and no play makes Jack a dull boy"', ['"Here\'s Johnny!"', '"Redrum"', '"Come play with us"']),
            $this->q(5002, 'easy', $c, 'What 2017 film features a clown named Pennywise?', 'It', ['Joker', 'Clown', 'Terrifier']),
            $this->q(5003, 'easy', $c, 'In "Jaws", what type of shark terrorizes the town?', 'Great white shark', ['Hammerhead', 'Tiger shark', 'Bull shark']),
            $this->q(5004, 'easy', $c, 'Who directed "Get Out" (2017)?', 'Jordan Peele', ['James Wan', 'Ari Aster', 'M. Night Shyamalan']),
            $this->q(5005, 'easy', $c, 'In "Psycho", what is the name of the motel?', 'Bates Motel', ['Norman Hotel', 'Fairvale Inn', 'Loomis Lodge']),
            $this->q(5101, 'medium', $c, 'In "Silence of the Lambs", what is Hannibal Lecter\'s profession?', 'Psychiatrist', ['Surgeon', 'Professor', 'Chef']),
            $this->q(5102, 'medium', $c, 'What found footage film launched the subgenre in 1999?', 'The Blair Witch Project', ['Paranormal Activity', 'Cloverfield', 'REC']),
            $this->q(5103, 'medium', $c, 'In "Hereditary", who directed the film?', 'Ari Aster', ['Jordan Peele', 'Robert Eggers', 'Mike Flanagan']),
            $this->q(5104, 'medium', $c, 'What Alfred Hitchcock film features birds attacking a coastal town?', 'The Birds (1963)', ['Psycho', 'Vertigo', 'Rear Window']),
            $this->q(5105, 'medium', $c, 'In "Scream", what is the name of the masked killer\'s identity?', 'Ghostface', ['Jason', 'Michael', 'Leatherface']),
            $this->q(5201, 'hard', $c, 'What Japanese horror film inspired the American "The Ring"?', '"Ringu" (1998) by Hideo Nakata', ['Ju-On', 'Dark Water', 'Audition']),
            $this->q(5202, 'hard', $c, 'In "The Exorcist", who directed the film?', 'William Friedkin', ['Wes Craven', 'William Peter Blatty', 'John Carpenter']),
            $this->q(5203, 'hard', $c, 'What is the "Final Girl" trope in horror?', 'The last woman standing who survives to confront the killer — coined by Carol Clover in "Men, Women, and Chain Saws"', ['The villain', 'A ghost', 'The narrator']),
            $this->q(5204, 'hard', $c, 'In "Midsommar", what real Swedish festival inspired the film?', 'Midsommar (Midsummer) — a traditional Swedish celebration of the summer solstice', ['Halloween', 'Christmas', 'Easter']),
            $this->q(5205, 'hard', $c, 'Who composed the "Halloween" (1978) theme music?', 'John Carpenter himself — using a 5/4 time signature on piano', ['Danny Elfman', 'Bernard Herrmann', 'Goblin']),
            $this->q(5301, 'expert', $c, 'What practical effect was used for the chest-burster scene in "Alien"?', 'A prosthetic chest with blood-filled bladders — the actors did not know what would happen, their reactions are genuine', ['CGI', 'A puppet', 'A real animal']),
            $this->q(5302, 'expert', $c, 'What is "giallo" and which director is its master?', 'Italian thriller/horror subgenre with elaborate murder sequences and mystery plots — Dario Argento is the master (Suspiria)', ['A pasta dish', 'A camera brand', 'A lighting technique']),
            $this->q(5303, 'expert', $c, 'In "The Texas Chain Saw Massacre" (1974), what was the $300,000 budget adjusted for inflation?', 'Approximately $1.8 million in today\'s dollars — grossed over $30 million', ['$100 million', '$10 million', '$50,000']),
            $this->q(5304, 'expert', $c, 'What film technique did Stanley Kubrick use in "The Shining" for the Steadicam?', 'Garrett Brown\'s Steadicam following Danny on his Big Wheel through the corridors — one of the first uses in horror', ['Handheld only', 'Tripod only', 'Crane shots']),
            $this->q(5305, 'expert', $c, 'What is the "Sunken Place" metaphor in "Get Out"?', 'A state of complete powerlessness and silencing — Jordan Peele has said it represents the marginalization of Black voices in America', ['A basement', 'A pool', 'A cave']),
            $this->q(5401, 'legendary', $c, 'What was the specific controversy around the "Cannibal Holocaust" (1980) director?', 'Ruggero Deodato was charged with murder — authorities believed the actors were actually killed; he had to prove they were alive in court', ['No controversy', 'Budget issues', 'A casting dispute']),
            $this->q(5402, 'legendary', $c, 'What was Georges Méliès\' contribution to horror cinema?', '"The House of the Devil" (1896) — one of the first horror films ever made, using stop-trick substitution effects', ['Nothing', 'Sound design', 'Color film']),
            $this->q(5403, 'legendary', $c, 'What was "The Cabinet of Dr. Caligari" (1920) and its importance?', 'A German Expressionist masterpiece with distorted sets and painted shadows — established visual horror language still used today', ['A comedy', 'A documentary', 'A musical']),
            $this->q(5404, 'legendary', $c, 'What specific production technique made "Paranormal Activity" cost only $15,000?', 'Shot in director Oren Peli\'s own house on consumer-grade video cameras with improvised dialogue over 7 days', ['Big studio budget', 'Film cameras', 'Green screen']),
            $this->q(5405, 'legendary', $c, 'What was the historical significance of "Nosferatu" (1922)?', 'An unauthorized adaptation of Dracula — Bram Stoker\'s estate sued and won, ordering all copies destroyed (some survived)', ['An original story', 'A sequel', 'A remake']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CLASSICS & AWARDS
    // ═══════════════════════════════════════════════════════════════════════
    private function classicsAwards(): array
    {
        $c = 'classics_awards';
        return [
            $this->q(6001, 'easy', $c, 'What film won the first ever Academy Award for Best Picture?', 'Wings (1927)', ['The Jazz Singer', 'Sunrise', 'Metropolis']),
            $this->q(6002, 'easy', $c, 'Who directed "Citizen Kane"?', 'Orson Welles', ['Alfred Hitchcock', 'John Ford', 'Billy Wilder']),
            $this->q(6003, 'easy', $c, 'What classic film features "Rosebud" as a central mystery?', 'Citizen Kane', ['Casablanca', 'The Maltese Falcon', 'Gone with the Wind']),
            $this->q(6004, 'easy', $c, 'In "Casablanca", what city does the film take place in?', 'Casablanca, Morocco', ['Paris', 'Cairo', 'Lisbon']),
            $this->q(6005, 'easy', $c, 'Who played Scarlett O\'Hara in "Gone with the Wind"?', 'Vivien Leigh', ['Bette Davis', 'Katharine Hepburn', 'Olivia de Havilland']),
            $this->q(6101, 'medium', $c, 'What film holds the record for most Oscar wins?', '"Ben-Hur" (1959), "Titanic" (1997), and "The Lord of the Rings: The Return of the King" (2003) with 11 each', ['Gone with the Wind', 'Schindler\'s List', 'The Godfather']),
            $this->q(6102, 'medium', $c, 'Who was the youngest person to win a competitive Oscar?', 'Tatum O\'Neal at age 10 for "Paper Moon" (1973)', ['Shirley Temple', 'Anna Paquin', 'Justin Henry']),
            $this->q(6103, 'medium', $c, 'What Hitchcock film is considered his masterpiece about obsessive love?', 'Vertigo', ['Psycho', 'Rear Window', 'North by Northwest']),
            $this->q(6104, 'medium', $c, '"The Godfather" won Best Picture in what year?', '1973 (for the 1972 film)', ['1974', '1971', '1975']),
            $this->q(6105, 'medium', $c, 'What film won the first Palme d\'Or at Cannes?', '"Marty" (1955) by Delbert Mann', ['La Dolce Vita', 'Blowup', 'The Third Man']),
            $this->q(6201, 'hard', $c, 'What 1927 film is considered the first "talkie"?', '"The Jazz Singer"', ['Metropolis', 'Sunrise', 'Wings']),
            $this->q(6202, 'hard', $c, 'What film was voted greatest of all time in Sight & Sound\'s 2022 poll?', '"Jeanne Dielman, 23 quai du Commerce, 1080 Bruxelles" (1975) by Chantal Akerman', ['Citizen Kane', 'Vertigo', '2001: A Space Odyssey']),
            $this->q(6203, 'hard', $c, 'Which studio system era filmmaker directed "It\'s a Wonderful Life"?', 'Frank Capra', ['John Ford', 'Howard Hawks', 'Billy Wilder']),
            $this->q(6204, 'hard', $c, 'What film won both Best Picture and the Palme d\'Or in the same year?', '"Parasite" (2019) — first non-English film, and "Marty" (1955)', ['The Artist', 'Moonlight', 'No Country for Old Men']),
            $this->q(6205, 'hard', $c, 'What was the "Hollywood Ten" and when?', 'Ten filmmakers blacklisted in 1947 for refusing to testify before HUAC about Communist ties', ['A film genre', 'A camera model', 'A list of films']),
            $this->q(6301, 'expert', $c, 'What was the Hays Code and when was it enforced?', 'A set of moral censorship guidelines (1934-1968) restricting violence, sexuality, and controversial content in Hollywood films', ['A camera patent', 'A film award', 'A studio contract']),
            $this->q(6302, 'expert', $c, 'What was Italian Neorealism\'s defining film according to most historians?', '"Rome, Open City" (1945) by Roberto Rossellini or "Bicycle Thieves" (1948) by Vittorio De Sica', ['La Dolce Vita', 'Cinema Paradiso', '8½']),
            $this->q(6303, 'expert', $c, 'How many times was Katharine Hepburn nominated for the Best Actress Oscar?', '12 times — winning 4 (a record for any actor)', ['5', '8', '3']),
            $this->q(6304, 'expert', $c, 'What was the "Paramount Decree" of 1948?', 'A Supreme Court ruling that forced major studios to divest their theater chains — ended the studio system oligopoly', ['A film award', 'A censorship law', 'A tax code']),
            $this->q(6305, 'expert', $c, 'What film has the most Oscar nominations without winning any?', '"The Turning Point" (1977) and "The Color Purple" (1985) with 11 nominations each and zero wins', ['Citizen Kane', 'Psycho', 'Vertigo']),
            $this->q(6401, 'legendary', $c, 'What was the Latham Loop and why was it essential to cinema?', 'A slack loop in film projectors that prevented tearing — invented by the Latham family (1895), enabling continuous projection of long films', ['A camera lens', 'A sound device', 'A lighting rig']),
            $this->q(6402, 'legendary', $c, 'What was the specific Technicolor three-strip process?', 'Three separate film strips (red, green, blue) recorded simultaneously through a prism beam-splitter — used in "The Wizard of Oz" and "Gone with the Wind"', ['Black and white', 'A filter', 'Digital color']),
            $this->q(6403, 'legendary', $c, 'What was the "Schüfftan process" and which film made it famous?', 'Using mirrors to combine miniature sets with live-action footage — "Metropolis" (1927) made it famous', ['A sound technique', 'An acting method', 'A writing style']),
            $this->q(6404, 'legendary', $c, 'What specific camera lens did Gregg Toland use for "Citizen Kane" and why?', 'Wide-angle lenses with small apertures for deep focus — keeping both foreground and background sharp simultaneously', ['Telephoto only', 'No lens', 'Fisheye']),
            $this->q(6405, 'legendary', $c, 'What was the Edison Manufacturing Company\'s role in early cinema?', 'Edison patented the Kinetoscope and tried to monopolize filmmaking through the Motion Picture Patents Company (the "Trust"), leading to independent studios moving to Hollywood', ['Nothing', 'Sound design only', 'Just distribution']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DIRECTORS & ACTORS
    // ═══════════════════════════════════════════════════════════════════════
    private function directorsActors(): array
    {
        $c = 'directors_actors';
        return [
            $this->q(7001, 'easy', $c, 'Who directed "Jaws", "E.T.", and "Schindler\'s List"?', 'Steven Spielberg', ['Martin Scorsese', 'Francis Ford Coppola', 'George Lucas']),
            $this->q(7002, 'easy', $c, 'Which actor is known for "The Godfather" and "Scarface"?', 'Al Pacino', ['Robert De Niro', 'Joe Pesci', 'Jack Nicholson']),
            $this->q(7003, 'easy', $c, 'Who directed "Inception" and "The Dark Knight"?', 'Christopher Nolan', ['Denis Villeneuve', 'David Fincher', 'Zack Snyder']),
            $this->q(7004, 'easy', $c, 'Which actress won Oscars for "Kramer vs. Kramer" and "Sophie\'s Choice"?', 'Meryl Streep', ['Cate Blanchett', 'Judi Dench', 'Kate Winslet']),
            $this->q(7005, 'easy', $c, 'Who directed "Pulp Fiction"?', 'Quentin Tarantino', ['Martin Scorsese', 'Guy Ritchie', 'David Lynch']),
            $this->q(7101, 'medium', $c, 'What Hitchcock technique is named after a "Vertigo" shot?', 'The "dolly zoom" (Vertigo effect) — dolly out while zooming in to create a disorienting perspective shift', ['A cut', 'A fade', 'A dissolve']),
            $this->q(7102, 'medium', $c, 'Who has directed the most Best Picture Oscar winners?', 'John Ford with 4 Best Director wins (though not all were Best Picture)', ['Steven Spielberg', 'William Wyler', 'Billy Wilder']),
            $this->q(7103, 'medium', $c, 'What director is known for symmetrical compositions and pastel colors?', 'Wes Anderson', ['David Lynch', 'Tim Burton', 'Sofia Coppola']),
            $this->q(7104, 'medium', $c, 'Who played Tony Montana in "Scarface"?', 'Al Pacino', ['Robert De Niro', 'James Caan', 'Ray Liotta']),
            $this->q(7105, 'medium', $c, 'What Japanese director is called the "Emperor of Cinema"?', 'Akira Kurosawa', ['Hayao Miyazaki', 'Yasujirō Ozu', 'Kenji Mizoguchi']),
            $this->q(7201, 'hard', $c, 'What is Method acting and who popularized it in Hollywood?', 'A technique where actors fully immerse in their character\'s psychology — Lee Strasberg adapted Stanislavski\'s system at the Actors Studio', ['Memorizing lines', 'Reading scripts', 'Watching films']),
            $this->q(7202, 'hard', $c, 'Who is the most nominated living person at the Oscars?', 'John Williams with over 50 nominations', ['Steven Spielberg', 'Meryl Streep', 'Kathleen Kennedy']),
            $this->q(7203, 'hard', $c, 'What cinematographer has won the most Oscars?', 'Emmanuel Lubezki with 3 consecutive wins (Gravity, Birdman, The Revenant)', ['Roger Deakins', 'Janusz Kamiński', 'Robert Richardson']),
            $this->q(7204, 'hard', $c, 'Who directed "Rashomon" (1950)?', 'Akira Kurosawa', ['Kenji Mizoguchi', 'Yasujirō Ozu', 'Masaki Kobayashi']),
            $this->q(7205, 'hard', $c, 'What actor has the most Oscar nominations without winning?', 'Peter O\'Toole with 8 nominations (received an honorary Oscar)', ['Glenn Close', 'Albert Finney', 'Richard Burton']),
            $this->q(7301, 'expert', $c, 'What specific filming technique did Andrei Tarkovsky pioneer?', 'Extremely long, unbroken takes with slow, deliberate camera movement — "sculpting in time"', ['Fast cutting', 'CGI', 'Handheld camera']),
            $this->q(7302, 'expert', $c, 'What was the "Meisner Technique" in acting?', 'Developed by Sanford Meisner — emphasizes living truthfully in imaginary circumstances through repetition exercises', ['Stage fighting', 'Voice projection', 'Dance']),
            $this->q(7303, 'expert', $c, 'Who is Roger Deakins and what makes his cinematography distinctive?', 'A British cinematographer known for naturalistic lighting and precise composition — shot "Blade Runner 2049", "1917", "No Country for Old Men"', ['A director', 'An actor', 'A producer']),
            $this->q(7304, 'expert', $c, 'What was Terrence Malick\'s approach in "The Thin Red Line"?', 'Shot with natural light, encouraged improvisation, used philosophical voiceovers, and cut many famous actors\' roles entirely in editing', ['Standard script reading', 'All CGI', 'Documentary style']),
            $this->q(7305, 'expert', $c, 'What was Stanley Kubrick\'s approach to "Barry Lyndon" lighting?', 'Shot entirely by candlelight using ultra-fast NASA Zeiss f/0.7 lenses — no artificial lighting in interior scenes', ['Normal studio lights', 'Only daylight', 'Fluorescent lights']),
            $this->q(7401, 'legendary', $c, 'What was the "Kuleshov Effect" and its significance?', 'Soviet filmmaker Lev Kuleshov demonstrated that the same shot paired with different images creates different emotional interpretations — foundational to montage theory', ['A camera lens', 'A sound design', 'A costume technique']),
            $this->q(7402, 'legendary', $c, 'What specific technique defines Andrei Tarkovsky\'s "Mirror" (1975)?', 'Non-linear autobiographical narrative weaving documentary, poetry, dreams, and memories — with camera movements that transition between time periods within single shots', ['Standard editing', 'Fast cuts', 'CGI']),
            $this->q(7403, 'legendary', $c, 'What was the "Soviet Montage Theory" and its key proponents?', 'Eisenstein, Pudovkin, and Kuleshov theorized that editing (montage) creates meaning through juxtaposition of shots — led to "intellectual montage" in "Battleship Potemkin"', ['A camera brand', 'A lighting technique', 'A sound format']),
            $this->q(7404, 'legendary', $c, 'What was Gordon Willis\'s nickname and his technique?', '"The Prince of Darkness" — used underexposed, low-key lighting in "The Godfather" (faces half in shadow), which was controversial at the time', ['The King of Light', 'The Master of Sound', 'The Duke of Color']),
            $this->q(7405, 'legendary', $c, 'What was the Dziga Vertov Group and their filmmaking approach?', 'A radical collective led by Jean-Luc Godard in the late 1960s-70s — Marxist films rejecting traditional narrative cinema', ['A camera brand', 'A Hollywood studio', 'An animation studio']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MUSIC & QUOTES
    // ═══════════════════════════════════════════════════════════════════════
    private function musicQuotes(): array
    {
        $c = 'music_quotes';
        return [
            $this->q(8001, 'easy', $c, '"May the Force be with you" is from which movie?', 'Star Wars', ['Star Trek', 'Guardians of the Galaxy', 'The Matrix']),
            $this->q(8002, 'easy', $c, '"I\'ll be back" is a famous line from which film?', 'The Terminator', ['Predator', 'Total Recall', 'Commando']),
            $this->q(8003, 'easy', $c, 'Who composed the "Star Wars" theme?', 'John Williams', ['Hans Zimmer', 'Danny Elfman', 'James Horner']),
            $this->q(8004, 'easy', $c, '"Let It Go" is a song from which Disney movie?', 'Frozen', ['Moana', 'Tangled', 'The Little Mermaid']),
            $this->q(8005, 'easy', $c, 'What movie features the song "My Heart Will Go On"?', 'Titanic', ['The Notebook', 'A Star Is Born', 'Ghost']),
            $this->q(8101, 'medium', $c, '"Frankly, my dear, I don\'t give a damn" is from which film?', 'Gone with the Wind', ['Casablanca', 'Citizen Kane', 'A Streetcar Named Desire']),
            $this->q(8102, 'medium', $c, 'Who composed the "Schindler\'s List" score?', 'John Williams (with Itzhak Perlman on violin)', ['Hans Zimmer', 'James Horner', 'Howard Shore']),
            $this->q(8103, 'medium', $c, '"I coulda been a contender" is from which film?', 'On the Waterfront', ['Rocky', 'Raging Bull', 'Million Dollar Baby']),
            $this->q(8104, 'medium', $c, 'Who composed "The Lord of the Rings" film scores?', 'Howard Shore', ['John Williams', 'Hans Zimmer', 'Danny Elfman']),
            $this->q(8105, 'medium', $c, '"What\'s in the box?" is from which thriller?', 'Se7en', ['The Ring', 'Saw', 'Zodiac']),
            $this->q(8201, 'hard', $c, '"Rosebud" — what does this word represent in "Citizen Kane"?', 'Charles Foster Kane\'s childhood sled, symbolizing lost innocence', ['A flower', 'A woman', 'A ship']),
            $this->q(8202, 'hard', $c, 'Who composed the original "Psycho" shower scene music?', 'Bernard Herrmann — using only string instruments', ['John Williams', 'Alfred Hitchcock', 'Max Steiner']),
            $this->q(8203, 'hard', $c, '"Forget it, Jake. It\'s Chinatown." — what film ends with this line?', 'Chinatown (1974)', ['The Big Lebowski', 'L.A. Confidential', 'The Maltese Falcon']),
            $this->q(8204, 'hard', $c, 'What film features "Also sprach Zarathustra"?', '2001: A Space Odyssey', ['Star Wars', 'Interstellar', 'Close Encounters']),
            $this->q(8205, 'hard', $c, 'Who composed the "Blade Runner" (1982) soundtrack?', 'Vangelis', ['Hans Zimmer', 'John Williams', 'Tangerine Dream']),
            $this->q(8301, 'expert', $c, 'What musical concept did John Williams use for "Star Wars" called "leitmotif"?', 'A recurring musical theme associated with a specific character, place, or idea', ['A single melody', 'Random notes', 'Sound effects']),
            $this->q(8302, 'expert', $c, 'What specific musical interval creates the "Jaws" theme\'s tension?', 'A minor second (semitone) — the smallest interval, inherently dissonant', ['An octave', 'A perfect fifth', 'A major third']),
            $this->q(8303, 'expert', $c, 'What specific synthesizer did Vangelis use for "Blade Runner"?', 'Yamaha CS-80 synthesizer', ['A piano', 'A guitar', 'A Minimoog']),
            $this->q(8304, 'expert', $c, 'What musical technique does Hans Zimmer use in "Dunkirk"?', 'A Shepard tone — an auditory illusion of endlessly ascending pitch', ['A simple melody', 'Silence', 'Jazz improvisation']),
            $this->q(8305, 'expert', $c, 'What is the "Wilhelm Scream"?', 'A stock sound effect from "Distant Drums" (1951) — used in hundreds of films as an industry in-joke', ['A new sound', 'A rare effect', 'A music piece']),
            $this->q(8401, 'legendary', $c, 'What specific musical structure did Williams use for "Star Wars" referencing Wagner?', 'Leitmotif system inspired by Wagner\'s Ring Cycle — each character and concept has a recurring musical theme', ['Random composition', 'Jazz standards', 'Pop songs']),
            $this->q(8402, 'legendary', $c, 'What exact year did the first synchronized film score appear?', '"Don Juan" (1926) had the first synchronized Vitaphone orchestral score', ['1930', '1940', '1915']),
            $this->q(8403, 'legendary', $c, 'What was Bernard Herrmann\'s influence on film scoring?', 'Rejected the "big melody" approach, using short motifs and dissonance for psychological tension', ['He wrote pop songs', 'He used only piano', 'He improvised everything']),
            $this->q(8404, 'legendary', $c, 'What happened to Alex North\'s "2001" score?', 'Kubrick rejected it completely at the last minute, using the temp track instead — North didn\'t know until the premiere', ['It was used', 'North approved', 'It was mixed in']),
            $this->q(8405, 'legendary', $c, 'What was the "Fantasound" system created for Disney\'s "Fantasia"?', 'An early stereophonic surround sound system — the first commercial film surround format', ['Just mono', 'Standard stereo', 'Digital surround']),
        ];
    }
}
