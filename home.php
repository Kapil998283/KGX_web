<?php
require_once 'config/database.php';
include 'includes/header.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch hero settings from database
$stmt = $db->query("SELECT * FROM hero_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$hero_settings = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<main>
    <article>

      <!-- 
        - #HERO
      -->

      <!-- #HERO -->
      <section class="hero" id="hero">
        <div class="container">
          <p class="hero-subtitle"><?php echo htmlspecialchars($hero_settings['subtitle'] ?? 'THE SEASON 1'); ?></p>
      
          <h1 class="h1 hero-title"><?php echo htmlspecialchars($hero_settings['title'] ?? 'TOURNAMENTS'); ?></h1>
      
          <div class="btn-group">
            <a href="/Shop/index.php">
              <button class="btn btn-primary">
                <ion-icon name="<?php echo htmlspecialchars($hero_settings['primary_btn_icon'] ?? 'wallet-outline'); ?>"></ion-icon>
                <span><?php echo htmlspecialchars($hero_settings['primary_btn_text'] ?? '+TICKET'); ?></span>
              </button>
            </a>
            
            <a href="<?php echo htmlspecialchars($hero_settings['secondary_btn_url'] ?? '#games'); ?>">
              <button class="btn btn-link">
                <?php echo htmlspecialchars($hero_settings['secondary_btn_text'] ?? 'GAMES'); ?>
                <ion-icon name="<?php echo htmlspecialchars($hero_settings['secondary_btn_icon'] ?? 'game-controller-outline'); ?>"></ion-icon>
              </button>
            </a>
          </div>
        </div>
      </section>
      
      




      <div class="section-wrapper">


<!-- 
  - #TOURNAMENT
-->

<section class="tournament" id="tournament">
  <div class="container">

    <div class="tournament-content">

      <h2 class="h3 tournament-subtitle">Our Weekly</h2>

      <p class="tournament-title">Gaming Tournaments!</p>

      <p class="tournament-text">
        Here you can register for the weekly tournament to win a big prize pool.
      </p>

      <a href="pages/tournaments.php"><button class="btn btn-primary">Register</button></a>

    </div>

    <div class="tournament-prize">

      <h2 class="h3 tournament-prize-title">Prize Pool</h2>

      <data value="10000">Highest Prize Pool</data>

      <figure>
        <img src="/assets/images/prize-img.png" alt="Prize image">
      </figure>

    </div>

    <div class="tournament-winners">

      <h2 class="h3 tournament-winners-title"> Make Our Teams</h2>

      <ul class="tournament-winners-list">

        <li>
          <div class="winner-card">

            <figure class="card-banner">
              <img src="/assets/images/team-member-1.png" alt="Winner image">
            </figure>

            <a href="pages/create_team.php"><button class="btn btn-secondary">Join a Team</button></a>

          </div>
        </li>

        <li>
          <div class="winner-card">

            <figure class="card-banner">
              <img src="/assets/images/team-member-2.png" alt="Winner image">
            </figure>

            <a href="pages/create_team.php"><button class="btn btn-secondary">Create a Team</button></a>

          </div>
        </li>

      </ul>

    </div>

  </div>
</section>


      
        <!-- 
          - #games
        -->

        <section class="games" id="games">
          <div class="container">

            <h2 class="h2 section-title">check our games</h2>

            <ul class="games-list">

              <li>
                <div class="games-card">

                  <div class="card-banner">

                    <a href="#">
                      <img src="/assets/images/bgmi.png" alt="Headphone">
                    </a>

                    <button class="share">
                      <ion-icon name="share-social"></ion-icon>
                    </button>

                    <div class="card-time-wrapper">
                      
                      <ion-icon name="time-outline"></ion-icon>
                      <span>2 maps</span>
                    </div>

                  </div>

                  <div class="card-content">

                    <div class="card-title-wrapper">

                      <h3 class="h3 card-title">PUBG</h3>

                      <p class="card-subtitle">e-sports</p>

                    </div>

                    <div class="card-prize">Room</div>

                  </div>

                  <div class="card-actions">

                    <a href="pages/games/pubg.php">
                      <button class="btn btn-primary">
                        <ion-icon name="add-outline"></ion-icon>
                        <span>participate</span>
                      </button>
                    </a>

                  </div>

                </div>
              </li>

              <li>
                <div class="games-card">

                  <div class="card-banner">

                    <a href="#">
                      <img src="/assets/images/bgmi.png" alt="Controller">
                    </a>

                    <button class="share">
                      <ion-icon name="share-social"></ion-icon>
                    </button>

                    <div class="card-time-wrapper">
                      <ion-icon name="time-outline"></ion-icon>

                      <span>All MAPS</span>
                    </div>

                  </div>

                  <div class="card-content">

                    <div class="card-title-wrapper">

                      <h3 class="h3 card-title">Bgmi</h3>

                      <p class="card-subtitle">e-sports</p>

                    </div>

                    <div class="card-prize">Room</div>

                  </div>

                  <div class="card-actions">

                    <a href="pages/games/bgmi.php">
                      <button class="btn btn-primary">
                        <ion-icon name="add-outline"></ion-icon>
                        <span>participate</span>
                      </button>
                    </a>

                  </div>

                </div>
              </li>

              <li>
                <div class="games-card">

                  <div class="card-banner">

                    <a href="#">
                      <img src="/assets/images/freefire.png" alt="Gaming mask">
                    </a>

                    <button class="share">
                      <ion-icon name="share-social"></ion-icon>
                    </button>

                    <div class="card-time-wrapper">
                      <ion-icon name="time-outline"></ion-icon>

                      <span>2 maps</span>
                    </div>

                  </div>

                  <div class="card-content">

                    <div class="card-title-wrapper">

                      <h3 class="h3 card-title">free fire</h3>

                      <p class="card-subtitle">e-sports</p>

                    </div>

                    <div class="card-prize">ROOM</div>

                  </div>

                  <div class="card-actions">

                    <a href="pages/games/freefire.php">
                      <button class="btn btn-primary">
                        <ion-icon name="add-outline"></ion-icon>
                        <span>participate</span>
                      </button>
                    </a>

                  </div>

                </div>
              </li>

              <li>
                <div class="games-card">

                  <div class="card-banner">

                    <a href="#">
                      <img src="/assets/images/cod.png" alt="Headphone">
                    </a>

                    <button class="share">
                      <ion-icon name="share-social"></ion-icon>
                    </button>

                    <div class="card-time-wrapper">
                      
                      <ion-icon name="time-outline"></ion-icon>
                      <span>2 maps</span>
                    </div>

                  </div>

                  <div class="card-content">

                    <div class="card-title-wrapper">

                      <h3 class="h3 card-title">COD</h3>

                      <p class="card-subtitle">e-sports</p>

                    </div>

                    <div class="card-prize">Room</div>

                  </div>

                  <div class="card-actions">

                    <a href="pages/games/cod.php">
                      <button class="btn btn-primary">
                        <ion-icon name="add-outline"></ion-icon>
                        <span>participate</span>
                      </button>
                    </a>

                  </div>

                </div>
              </li>

            </ul>

          </div>
        </section>



      <!-- 
    - #ABOUT SECTION
-->
<section class="about" id="about">
  <div class="container">

    <figure class="about-banner">

      <img src="/assets/images/about-img.png" alt="M shape" class="about-img">

      <img src="/assets/images/character-1.png" alt="Game character" class="character character-1">

      <img src="/assets/images/character-2.png" alt="Game character" class="character character-2">

      <img src="/assets/images/character-3.png" alt="Game character" class="character character-3">

    </figure>
    <!-- Left Side: About Content -->
    <div class="about-content">
      <p class="about-subtitle">• participate • Earn • Enjoy</p>

      <h2 class="about-title">
        Ultimate <strong>Gamer's</strong> Haven
      </h2>

      <p class="about-text">
        Dive into the ultimate gaming experience where skills meet rewards! Compete, win, and climb the leaderboards
        in an esports world designed just for you.
      </p>

      <p class="about-bottom-text">
        <ion-icon name="arrow-forward-circle-outline"></ion-icon>
        <span>Will sharpen your brain and focus</span>
      </p>
    </div>

  </div>
</section>

 


        <!-- 
          - #community
        -->

        <section class="community">
          <div class="container">

            <div class="community-card">

              <div class="community-content">
                <figure class="community-img">
                  <img src="/assets/images/newsletter-img.png" alt="community image">
                </figure>

                <h2 class="h2 community-title">Subscribe to our community</h2>
              </div>

              <form action="" class="community-form">
                <input type="email" name="email" required placeholder="Your Email Address" class="input-field">

                <button type="submit" class="btn btn-secondary">Subscribe</button>
              </form>

            </div>

          </div>
        </section>

      </div>

    </article>
  </main>






<?php include 'includes/footer.php'; ?> 