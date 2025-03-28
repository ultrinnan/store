<?php
/*
 * Template Name: Articles page
 */

get_header();
?>
    <section class="events">
        <div class="container">
            <div class="content">
                <div class="article">
					<?php if (have_posts()): while (have_posts()): the_post(); ?>
						<?php the_content(); ?>

              <style>

                .palette {
                  display: flex;
                  flex-wrap: wrap;
                  gap: 1rem;
                }

                .color {
                  width: 120px;
                  height: 120px;
                  border: 2px solid #ccc;
                  box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.2);
                  display: flex;
                  flex-direction: column;
                  justify-content: center;
                  align-items: center;
                  text-align: center;
                  font-size: 0.85rem;
                }

                .label {
                  margin-top: 0.5rem;
                }
              </style>

            <h1>Vintage Scroll Color Palette</h1>
            <div class="palette">

              <!-- Texts -->
              <div class="color" style="background-color: #3b2f2f; color: white;">
                <div class="label">#3b2f2f<br>(ink)</div>
              </div>
              <div class="color" style="background-color: #4a3c2f; color: white;">
                <div class="label">#4a3c2f</div>
              </div>
              <div class="color" style="background-color: #5b3a29; color: white;">
                <div class="label">#5b3a29</div>
              </div>
              <div class="color" style="background-color: #2e1f17; color: white;">
                <div class="label">#2e1f17</div>
              </div>

              <!-- Accents -->
              <div class="color" style="background-color: #6b4c3b; color: white;">
                <div class="label">#6b4c3b</div>
              </div>
              <div class="color" style="background-color: #a67c52;">
                <div class="label">#a67c52</div>
              </div>
              <div class="color" style="background-color: #996633;">
                <div class="label">#996633</div>
              </div>

              <!-- Decorative -->
              <div class="color" style="background-color: #bfa86a;">
                <div class="label">#bfa86a<br>(gold)</div>
              </div>
              <div class="color" style="background-color: #d4af37;">
                <div class="label">#d4af37</div>
              </div>
              <div class="color" style="background-color: #6e2c2c; color: white;">
                <div class="label">#6e2c2c</div>
              </div>
              <div class="color" style="background-color: #8b0000; color: white;">
                <div class="label">#8b0000</div>
              </div>

              <!-- Natural pigments -->
              <div class="color" style="background-color: #556b2f; color: white;">
                <div class="label">#556b2f<br>(earthy green)</div>
              </div>
              <div class="color" style="background-color: #3b5f79; color: white;">
                <div class="label">#3b5f79<br>(indigo)</div>
              </div>
              <div class="color" style="background-color: #445566; color: white;">
                <div class="label">#445566</div>
              </div>
            </div>



          <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php get_footer(); ?>