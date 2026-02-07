# Modernization Plan: F1 Manager Suite Frontend

## 1. Goal
Transition the current frontend logic (jQuery/Vanilla JS) to a modern, component-based architecture using **React** (standard in WordPress ecosystem) or **Vue.js**. This will improve maintainability, state management (especially for the betting game), and user experience (smoother interactions).

## 2. Architecture: "App Islands"
Instead of a full Single Page Application (SPA), we will use an "App Islands" approach. This means we mount small, interactive React/Vue apps into specific DOM elements where needed (e.g., the betting game shortcode, the login modal).

### Key Components to Modernize
1.  **Login Modal (`f1-login.js`)**:
    -   **Current**: Vanilla JS with AJAX.
    -   **New**: `LoginApp` component. Handles authentication state, form validation, and error messages via REST API.
2.  **Tippspiel (Betting Game) (`f1-tippspiel.js`)**:
    -   **Current**: jQuery/Vanilla JS, heavy DOM manipulation.
    -   **New**: `TippspielApp` component.
    -   **State**: Fetches current round, user tips, and results. Handles dynamic "saving" states and live updates.
3.  **Ticker (`f1-ticker.js`)**:
    -   **Current**: JS polling.
    -   **New**: `TickerApp` component. Uses React Query (TanStack Query) for efficient polling and caching.
4.  **Countdown (`f1-countdown.js`)**:
    -   **Current**: JS interval.
    -   **New**: `CountdownApp` component.

## 3. Technology Stack

### Build Tool
-   **@wordpress/scripts**: Standard WordPress build tool (based on Webpack).
-   **Why**: Automatically handles dependency extraction (e.g., `wp-element`, `wp-i18n`) and browser compatibility.

### Framework
-   **React (via `wp-element`)**: Since WordPress bundles React, using it reduces the final bundle size significantly.

### State Management
-   **Zustand** or **React Context**: Lightweight state management for sharing data (e.g., user session) between components if needed.

### Data Fetching
-   **TanStack Query (React Query)**: Excellent for managing server state (REST API data), caching, and polling (Ticker).

## 4. Implementation Steps

### Step 1: Setup Build Environment
1.  Initialize `package.json`.
2.  Install `@wordpress/scripts`.
3.  Configure `webpack.config.js` (optional, usually defaults work).

### Step 2: Create React Root
1.  Create `src/index.js`.
2.  Scan the DOM for target elements (e.g., `<div id="f1-tippspiel-root">`).
3.  Mount React components into these roots using `createRoot`.

### Step 3: API Enhancements (PHP)
1.  **Login API**: Ensure `F1_Login` exposes a robust REST endpoint (`/wp-json/f1manager/v1/login`) that returns a nonce and user data, instead of relying on `admin-ajax.php`.
2.  **Profile API**: Ensure profile data can be read/written via REST.
3.  **Tippspiel API**: Already exists (`F1_Tippspiel::register_api`). Review for completeness.

### Step 4: Component Migration
1.  **Tippspiel**: Port the logic from `f1-tippspiel.js` to React components (`RaceList`, `BetForm`, `Leaderboard`).
2.  **Ticker**: Port `f1-ticker.js` logic. Use `useQuery` for auto-fetching.

### Step 5: Enqueueing
1.  Update `includes/class-f1-*.php` files to enqueue the *built* script (`build/index.js`) instead of the raw JS files.
2.  Pass data (nonces, API URLs) via `wp_localize_script`.

## 5. Timeline & Effort
-   **Setup**: 1-2 hours.
-   **API Updates**: 2-4 hours.
-   **Login Component**: 4-6 hours.
-   **Tippspiel Component**: 8-12 hours (complex logic).
-   **Ticker/Countdown**: 2-4 hours.
-   **Testing**: 4-6 hours.

**Total Estimated Effort**: ~3-5 Days.
