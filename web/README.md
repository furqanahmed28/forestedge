# ForestEdge — Web Monitoring Dashboard & Ingestion API (`web/`)

This directory houses the operator-facing monitoring dashboard, incident management portal, and telemetry ingestion API for **ForestEdge**. It provides real-time situational awareness for forest rangers, conservation authorities, and emergency response teams by centralizing telemetry and VLM-generated anomaly alerts from distributed field edge nodes.

---

## Key Capabilities & Features

* **Real-Time Situation Dashboard (`index.php`)**:
  * Live KPI cards: Active Critical Alerts, Offline Nodes, Highest Risk Cluster, Average Acknowledgment Time, Ambient Temperature, and Relative Humidity.
  * **Interactive Geospatial Map**: Leaflet.js-powered cluster map (customized for Haripur Forest sector monitoring) visualizing deployed nodes, active clusters, and live alert statuses.
  * **Dynamic Alert Feed**: Real-time unacknowledged incident cards with direct action workflows.
* **Incident Lifecycle & Audit Trail (`process_event.php`, `event_detail.php`)**:
  * Formal incident response actions: **Acknowledge (`ACK`)** or **Reject / False Alarm (`FALSE-EVENT`)**.
  * Complete audit logging with operator ID, action timestamp, and field notes stored in `event_audit_logs`.
* **Telemetry & Ingestion REST API (`api.php`)**:
  * Lightweight, token-authenticated JSON endpoint receiving periodic heartbeats and VLM anomaly alerts from field gateways (`MasterNode`).
* **Node & Sector Management (`nodes.php`, `clusters.php`)**:
  * Register edge nodes with hardware MAC, node type (`Master`, `SubMaster`, `Leaf`), and GPS coordinates (latitude / longitude).
  * Automatically marks nodes as **Offline** if no heartbeat is received within 120 seconds.
* **Role-Based Authentication (`login.php`, `users.php`)**:
  * Session-based login with bcrypt password hashing supporting `ADMIN` and `RANGER` roles.

---

## Application Architecture & Data Flow

```
+-----------------------------------------------------------------------------------+
| Field Master Node (raspberry/core/roles.py)                                       |
|  - Standalone sensor/camera inference OR aggregated SubMaster alerts              |
|  - Buffered POST requests over cellular / satellite link                          |
+-----------------------------------------------------------------------------------+
                                         |
                                         | HTTPS POST /api.php?key=FE_SECRET_2026
                                         v
+-----------------------------------------------------------------------------------+
| Web API Layer (web/api.php)                                                       |
|  - Validates API key                                                              |
|  - Updates node heartbeat & active state                                          |
|  - Filters out non-anomalies ("NOTHING")                                          |
|  - Inserts critical threats (FIRE, DISEASE, LOGGING, OTHER) into SQLite database  |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
| SQLite Relational Store (web/database/forestedge.sqlite)                          |
|  - clusters, nodes, events, event_audit_logs, users                               |
+-----------------------------------------------------------------------------------+
                                         ^
                                         | Polling (fetch_data.php) & Actions
                                         v
+-----------------------------------------------------------------------------------+
| Operator Dashboard UI (web/index.php, layout.php)                                 |
|  - Leaflet Map, KPI metrics, Alert Feed, Audit Logs                               |
+-----------------------------------------------------------------------------------+
```

---

## Telemetry Ingestion API Specification (`api.php`)

### Authentication

All API requests require the `key` query parameter:

```
POST /api.php?key=FE_SECRET_2026
Content-Type: application/json
```

> [!TIP]
> In production environments, store the API key in an environment variable or protected configuration file rather than hardcoding it in source files.

---

### Endpoint Payloads

#### 1. Node Heartbeat Payload

Edge nodes transmit heartbeats every 30 seconds to confirm connectivity and health:

```json
{
  "type": "heartbeat",
  "cluster_id": "01",
  "node_id": "N01M01",
  "status": "Healthy",
  "timestamp": 1740000000
}
```

* **Action**: Updates `last_heartbeat = NOW()` and ensures `is_active = 1` for the specified `node_id`.

#### 2. Incident Telemetry Payload

Transmitted whenever local edge inference detects a non-normal forest condition (`Problem != "NOTHING"`):

```json
{
  "type": "telemetry",
  "cluster_id": "01",
  "node_id": "N01M01",
  "generated_at": 1740000000,
  "env_data": {
    "temperature": 34.2,
    "humidity": 42.1
  },
  "inference": {
    "Problem": "FIRE",
    "Criticality": "HIGH",
    "Description": "Active canopy blaze detected near sector boundary"
  }
}
```

* **Action**:
  1. Refreshes the node's heartbeat.
  2. Generates a unique collision-resistant event ID (e.g., `N01M01202611241530-42`).
  3. Inserts a record into the `events` table with `is_acknowledged = 0`.
  4. Returns `{"status": "success"}` with HTTP 200.

---

## Database Schema (`web/database/forestedge.sqlite`)

The backend uses a normalized SQLite database. The schema consists of five core tables:

### 1. `clusters`
Defines monitored forest regions or sectors.
* `cluster_id` (TEXT, PK): Unique sector identifier (e.g., `"01"`).
* `region_name` (TEXT): Descriptive sector name (e.g., `"Haripur Forest North"`).
* `created_at` (DATETIME): Registration timestamp.

### 2. `nodes`
Registers hardware edge devices and tracks physical state.
* `node_id` (TEXT, PK): Unique device identifier (e.g., `"N01M01"`).
* `cluster_id` (TEXT, FK): Parent cluster identifier.
* `hardware_mac` (TEXT): Hardware MAC address.
* `node_type` (TEXT): Hardware role (`Master`, `SubMaster`, `Leaf`).
* `latitude`, `longitude` (REAL): GPS coordinates for map plotting.
* `is_active` (INTEGER): Active flag (`1` = Online, `0` = Offline).
* `last_heartbeat` (DATETIME): Last recorded heartbeat timestamp.

### 3. `events`
Stores ingested threat alerts and environmental context.
* `event_id` (TEXT, PK): Unique incident identifier.
* `node_id` (TEXT, FK), `cluster_id` (TEXT, FK)
* `event_type` (TEXT): Classification category (`FIRE`, `DISEASE`, `LOGGING`, `OTHER`).
* `criticality` (TEXT): Severity tier (`LOW`, `MEDIUM`, `HIGH`).
* `temperature`, `humidity` (REAL): Ambient sensor readings at time of incident.
* `llm_summary` (TEXT): Vision-language model reasoning and description.
* `captured_at`, `generated_at`, `received_at` (DATETIME): Timestamp pipeline.
* `is_acknowledged` (INTEGER): `0` = Open / Action Required, `1` = Processed.

### 4. `event_audit_logs`
Complete traceability log of ranger responses.
* `log_id` (TEXT, PK): Audit record identifier.
* `event_id` (TEXT, FK): Associated incident.
* `user_id` (TEXT, FK): Ranger / Admin who took action.
* `action_type` (TEXT): `ACK` (Acknowledged) or `FALSE-EVENT` (Dismissed).
* `notes` (TEXT): Freeform operational notes.
* `action_timestamp` (DATETIME): Recorded action timestamp.

### 5. `users`
Portal user credentials and roles.
* `user_id` (TEXT, PK): Internal user identifier.
* `email` (TEXT, UNIQUE): Login email address.
* `password_hash` (TEXT): Bcrypt password hash.
* `role` (TEXT): Access tier (`ADMIN` or `RANGER`).
* `created_at` (DATETIME): User creation timestamp.

---

## File Structure & Responsibilities

| File | Purpose / Role |
| :--- | :--- |
| `db.php` | Central PDO SQLite database connection and timezone setting (`Asia/Karachi`) |
| `api.php` | External REST endpoint for edge nodes (heartbeats & telemetry) |
| `layout.php` | Global UI header, CSS theme, navigation bar, and session guard |
| `index.php` | Main monitoring dashboard with KPI cards, Leaflet map, and live feed |
| `fetch_data.php` | AJAX polling endpoint returning current KPIs, node statuses, and alerts |
| `process_event.php` | Handles event acknowledgement and writes to audit logs |
| `event_detail.php` | Dedicated page displaying deep forensic details and history for an event |
| `events_list.php` | Comprehensive tabular log of all historical events |
| `nodes.php` | Administrative CRUD interface for registered sensor nodes |
| `clusters.php` | Administrative management for monitored forest clusters |
| `users.php` | Administrator user creation and role management |
| `login.php` / `logout.php` | Session authentication entrypoint and teardown |

---

## Local Setup & Quickstart

### Prerequisites

* PHP 8.1 or higher with `pdo_sqlite` extension enabled.
* SQLite3.

### Running with PHP Built-in Server

You can run the web dashboard locally with zero external dependencies:

```bash
# Navigate to the web folder
cd forestedge/web

# Start PHP built-in web server
php -S 0.0.0.0:8000
```

Open your browser and navigate to:
```
http://localhost:8000/login.php
```

### Default Credentials (Development Seed)

* **Email**: `admin@forestedge.local`
* **Password**: `password`

> [!CAUTION]
> Change the default admin password and regenerate the database before deploying to a public-facing network. Ensure the `web/database/` directory has proper file write permissions for the web server user (e.g., `www-data`).

---

## Testing API Ingestion with cURL

### 1. Test Node Heartbeat

```bash
curl -X POST "http://localhost:8000/api.php?key=FE_SECRET_2026" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "heartbeat",
    "cluster_id": "01",
    "node_id": "N01M01",
    "status": "Healthy"
  }'
```

Expected Response: `{"status":"success"}`

### 2. Test Critical Fire Alert

```bash
curl -X POST "http://localhost:8000/api.php?key=FE_SECRET_2026" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "telemetry",
    "cluster_id": "01",
    "node_id": "N01M01",
    "env_data": {"temperature": 38.5, "humidity": 30.2},
    "inference": {
      "Problem": "FIRE",
      "Criticality": "HIGH",
      "Description": "Heavy smoke and flame detected in pine canopy"
    }
  }'
```

Expected Response: `{"status":"success"}`. The incident will immediately appear on the live dashboard and map.

---

## Coding Agent Notes & Maintenance

* **Stateless API Ingestion**: Keep `api.php` lean and independent of session state so it can process hundreds of edge node heartbeats asynchronously without blocking.
* **Database Write Safety**: All multi-statement transactions (such as acknowledging an event and writing an audit log in `process_event.php`) must use `PDO::beginTransaction()` and `commit()`.
* **Configurable Secret**: Replace the hardcoded `FE_SECRET_2026` with `getenv('FORESTEDGE_API_KEY') ?: '...'` to simplify secret management across staging and production deployments.
