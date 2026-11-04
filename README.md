# Illuminate — Bi-Tech Challenge Solution

Laravel 13 application implementing every scripted step of the challenge as artisan commands.

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# set ILLUMINATE_TOKEN in .env
php artisan migrate
php artisan test
```

## Commands

| Stage | Command | Purpose |
|---|---|---|
| 2 | `illuminate:fetch-credentials` | Fetch the SSH key and database credentials |
| 2 | `illuminate:db:query "<sql>"` | Run read-only SQL on the remote PostgreSQL through an SSH tunnel |
| 2 | `illuminate:db:find-flag` | Scan every readable relation for `ILLUMINATE{...}` |
| 3 | `illuminate:import` | Mirror neighborhoods and incidents into local SQLite |
| 3 | `illuminate:donut-flag {neighborhood=NB-7A2F} [-v]` | Concatenate incident codes in the 0.5–2 km donut, nearest first |
| — | `illuminate:submit-repo <repo_url> <cv>` | Submit this repository and a PDF CV |

## Stage 2 — Beyond the tunnel

PostgreSQL only listens on the SSH host's `localhost:5432`, so `SshTunnel` runs
`ssh -N -L 127.0.0.1:<free port>:localhost:5432` and `ChallengeDatabase` registers a runtime
`pgsql` connection on that port for the duration of a callback.

- The private key is written to the private disk with `0600` (OpenSSH rejects looser permissions) and kept apart from the other credentials; both are git-ignored.
- `BatchMode`, `ExitOnForwardFailure` and `IdentitiesOnly` make failures fast and explicit; host keys go to a project-local `known_hosts`.
- The tunnel waits until the local port accepts connections, surfaces ssh's stderr if it dies, and is always closed in `finally`.
- Every remote query runs inside `SET TRANSACTION READ ONLY`.

## Stage 3 — Centroid of chaos

### `WithinDonut` relation

`Neighborhood::incidents()` returns `new WithinDonut($this, Incident::class, innerRadiusKm: 0.5, outerRadiusKm: 2.0)`,
a direct subclass of `Illuminate\Database\Eloquent\Relations\Relation`:

- **Lazy loading** — index-friendly bounding-box pre-filter, exact donut test, ordered by distance then key.
- **Eager loading** — one query for the union of every parent's bounding box; `match()` applies the same donut test and ordering per parent in PHP.
- **Existence queries** — `has`, `whereHas` and `withCount` use a correlated subquery reading the centroid from the parent row.
- Comparisons use squared distances, so the base query needs no `sqrt`.
- Numeric constants are inlined as literals: PDO binds floats as strings and SQLite compares an untyped expression against text as `number < text`, which silently matched nothing.

### Data decisions

The dataset contains 38 sequenced `alpha-N` incidents (the flag) and 200 `noise-N` decoys, several placed to punish subtle mistakes:

- **Coordinate order** — PostgreSQL `point` is `(x, y)`, but the data stores `(latitude, longitude)` (33.3, 44.4 is Baghdad). Swapping them scales longitude by `cos(44°)` instead of `cos(33°)`.
- **Centroid** — the polygon is a trapezoid, so the candidate definitions disagree. The vertex average (PostgreSQL `@@`) and the area-weighted centroid both drop flag characters and include 12–17 decoys; the bounding-box center isolates exactly the 38 sequenced incidents. Three decoys sit precisely on that center.
- **Distance model** — equirectangular, 111 km per degree, longitude scaled by `cos(centroid latitude)`. Under it the flag incidents sit at exact fractions (`7/13 + n/26` km), and the decoy `noise-74` lies exactly on the 0.5 km inner edge (squared distance `0.2499999999537`). Haversine puts that decoy 0.9 m inside the donut and corrupts the flag.
- **Timestamps** — `occurred_at` is converted to UTC in PostgreSQL before import, since Laravel stores datetimes without an offset.
