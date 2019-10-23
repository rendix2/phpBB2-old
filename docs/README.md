- clone this repo
- run composer install


News:
- We can say how much users show in memberlist, we don't use topics_per_page
- forums table use AI
- users table use AI
- instalation shows only avaible drivers
- pruning recount users posts
- returned search button
- we count users topics
- optimized for(....) loops
- using dibi
- using inner join instead of SELECT * FROM a, b ....
- added DB Maintenance mod
- improved admin index (my own improved Advanced Admin Index Stats)
- improved show size of files (from phpBB3)
- using <label> tag for inputs
- language select has fixed selected attribute
- removed icq, aim, msnm, yim
- improved groups management in admin
- fixed pagination in author search
- use htmlspecialchars()
- removed Mozzila navigation bar
- calling Session::begin() is called in init_userprefs()
- admin can select template engine
- admin cannot select if cookie is secure, its automatic
- users use longer passwords  
- improved online users on index page
- removed online users on forum page 
- ACP DO NOT create left menu automatically by including all admin_*.php files 
- ACP can have different password to login
- Another Online/Offline indicator
- improved install
- improved config.php file
  
Conventions for template:  
- L_ is a language variable
- S_ is something system related
- U_ is an URL
- C_ is constants
- D_ is database data
- F_ is form

For permissions (acl) it's like this:
- u_ for user permissions
- m_ for moderator permissions
- a_ for admin permissions
- f_ for forum specific permissions
