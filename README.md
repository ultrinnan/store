# Veldrin store template
WP template and dev environment

---

Docker-compose file was added for local development.

`docker compose up -d`

`docker compos down`

To remove totaly everything and rebuild use `docker compose down --rmi all`

To have local database just need to export it from live site via wp-migrate plugin and import it using db tool in IDE

---

## Frontend build

navigate to this template folder and run `npm install` and then just `gulp`

local site available at http://localhost:8000/