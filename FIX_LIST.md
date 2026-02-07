# Fix List: F1 Manager Suite

## Immediate Logic & Code Quality Fixes

1.  **Database Installation Logic**
    -   **Problem**: `install_db` is located inside `F1_Tippspiel` but handles tables for the entire suite.
    -   **Fix**: Move `install_db` logic to a new class `includes/class-f1-installer.php` or the main plugin file. Update the activation hook in `f1-manager-suite.php` to call this new location.

2.  **Singleton Pattern Consistency**
    -   **Problem**: `F1_Manager_Calendar` is instantiated with `new` in `f1-manager-suite.php`, while other classes use `get_instance()`.
    -   **Fix**: Update `F1_Manager_Calendar` to use the Singleton pattern (`get_instance()`) like the rest of the plugin. Update the initialization call in `f1-manager-suite.php`.

3.  **CSS "Pfusch" (Hackiness)**
    -   **Problem**: `F1_Theme_Tweaks` injects a large block of CSS via PHP string in `wp_add_inline_style`.
    -   **Fix**: Move this CSS content to a dedicated file `assets/css/f1-theme-tweaks-custom.css` and enqueue it properly.

4.  **Module Dependency Safety**
    -   **Problem**: `F1_Ticker` calls `F1_WM_Stand` methods directly without checking if the class exists.
    -   **Fix**: Wrap the call in `if ( class_exists('F1_WM_Stand') ) { ... }` or use dependency injection/checking to prevent fatal errors if the module is disabled.

## Modernization (Frontend)

5.  **Transition to React/Vue**
    -   **Problem**: The frontend relies on jQuery and vanilla JS, which is becoming harder to maintain for complex interactions like the betting game (Tippspiel).
    -   **Fix**: Introduce a build step (`npm`, `webpack` / `@wordpress/scripts`) and rewrite the interactive components (`Tippspiel`, `Ticker`, `Login`, `Countdown`) as React components.

## Security & Best Practices

6.  **REST API for All Modules**
    -   **Problem**: Some modules (`F1_Login`, `F1_Profile`) rely heavily on `admin-ajax.php`.
    -   **Fix**: Expose REST API endpoints for these modules (similar to `F1_Tippspiel`) to support the modern frontend and decouple logic from WordPress admin-ajax.

7.  **Escape Output**
    -   **Check**: Ensure all user-supplied data (Team names, Bios, etc.) is properly escaped on output (`esc_html`, `esc_attr`). (Preliminary check looks good, but rigorous verification is needed).
