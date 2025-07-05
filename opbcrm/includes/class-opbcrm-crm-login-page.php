<?php
if (!defined('ABSPATH')) exit;

class OPBCRM_CRM_Login_Page {
    public static function create_or_get_page() {
        $slug = 'CRM';
        $title = 'CRM Login';
        $glass_login = '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e0e7ef 0%,#c9d6ff 100%);font-family:Inter,sans-serif;">
        <div style="backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);background:rgba(255,255,255,0.55);border-radius:16px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.37);padding:48px 32px;max-width:350px;width:100%;text-align:center;">
            <h2 style="font-weight:700;font-size:2rem;margin-bottom:24px;color:#222;">CRM Login</h2>
            <form>
                <input type="text" placeholder="Username" style="width:100%;margin-bottom:16px;padding:12px;border-radius:8px;border:1px solid #e0e7ef;background:rgba(255,255,255,0.7);font-size:1rem;outline:none;">
                <input type="password" placeholder="Password" style="width:100%;margin-bottom:24px;padding:12px;border-radius:8px;border:1px solid #e0e7ef;background:rgba(255,255,255,0.7);font-size:1rem;outline:none;">
                <button type="submit" style="width:100%;padding:12px 0;border:none;border-radius:8px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);color:#fff;font-weight:600;font-size:1rem;cursor:pointer;box-shadow:0 2px 8px rgba(102,126,234,0.15);transition:background 0.2s;">Login</button>
            </form>
        </div>
    </div>';
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page) return $page->ID;
        $page_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $glass_login,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
        ]);
        return $page_id;
    }
}
