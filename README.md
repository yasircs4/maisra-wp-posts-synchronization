# Maisra posts synchronization
This WordPress plugin synchronizes posts when `publish,edit,trash` between two website, it create and config channel vai `wp-rest-api` and `JWT` auth connection.



----
## Tasks
- [x] 00- Config authentication connection channel vai `JWT`.
- [x] 01- Copy categories-audio.
- [x] 03- Copy categories-books.
- [x] 03- Copy categories-post.
- [x] 04- Copy audio-posts.
- [x] 05- Copy books-posts.
- [x] 06- Copy post-posts.
- [x] 07- Create user "xoosh" and make auth connection via jwt.
- [x] 08- Sync [post, audio, books] create new.
- [x] 09- Sync [post, audio, books] edit.
- [x] 10- Sync [post, audio, books] trash.
- [x] 11- Store result of request in db SUCCESS or FAILED
- [x] 11- Testing.
- [x] 12- Fix bug with cmb2 not_saved meta in xoosh.
- [x] 13- Display featured image from sync_thumbnail_url.
- [ ] 14- \|/ General style of xoosh for pages (single,categories...etc)
- [ ] 15- Push top live server.      <-----
    - [ ] New Theme.
    - [ ] Database.
    - [ ] Elementor.
    - [ ] install plugin `Maisra Synchronization` _manhaj_.
    - [ ] config plugin `Maisra Synchronization` _manhaj_.
- [x] 15- Live Testing.

----
## Actions
-[ ] create user -> bot-xoosh in xoosh-site
-[ ] install plugin -> `JWT Authentication for WP REST API`   in xoosh-site
-[ ] Setup JWT Authentication for the WP REST API (.htaccess, wp-config.php).   in xoosh-site
-[ ] update: C:\MAMP\htdocs\manhajonline\wp-content\themes\manhaj-theme-2020\options.php
-[ ] update: C:\MAMP\htdocs\manhajonline\wp-content\themes\manhaj-theme-2020\inc\maisra.meta-boxes.php
-[ ] update: C:\MAMP\htdocs\xoosh-site\wp-content\themes\manhaj-theme-2020\inc\maisra.meta-boxes.php
-[ ] update: C:\MAMP\htdocs\manhajonline\wp-content\themes\manhaj-theme-2020\inc\maisra.custom-post-types.functions.php
-[ ] update: C:\MAMP\htdocs\xoosh-site\wp-content\themes\manhaj-theme-2020\inc\maisra.custom-post-types.functions.php
-[ ] fix bug:   remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
-[ ] update: C:\MAMP\htdocs\xoosh-site\wp-content\themes\xoosh-2022

