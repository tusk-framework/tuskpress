<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

class AdminView
{
    public static function render(string $title, string $contentHtml, string $siteName = 'TuskPress', string $username = 'Administrator'): string
    {
        $initials = self::initial($username);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en-US">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title} &lsaquo; {$siteName}</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <!-- Glassmorphism & Premium Dark Mode Styles -->
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                :root {
                    --bg-deep:    #0a0b10;
                    --bg-card:    rgba(20, 22, 39, 0.6);
                    --bg-sidebar: rgba(16, 18, 35, 0.85);
                    --accent:     #6c63ff;
                    --accent-hover: #5b52e0;
                    --accent2:    #a78bfa;
                    --text:       #f1f5f9;
                    --text-muted: #94a3b8;
                    --border:     rgba(255, 255, 255, 0.08);
                    --success:    #10b981;
                    --warning:    #f59e0b;
                    --danger:     #ef4444;
                    --glass-blur: blur(12px);
                }
                body {
                    font-family: 'Inter', sans-serif;
                    background: var(--bg-deep) radial-gradient(circle at top right, rgba(108,99,255,0.1), transparent 40%);
                    color: var(--text);
                    display: flex;
                    min-height: 100vh;
                    overflow-x: hidden;
                }

                /* ── SIDEBAR ── */
                .sidebar {
                    width: 260px;
                    background: var(--bg-sidebar);
                    backdrop-filter: var(--glass-blur);
                    -webkit-backdrop-filter: var(--glass-blur);
                    border-right: 1px solid var(--border);
                    display: flex;
                    flex-direction: column;
                    position: fixed;
                    top: 0; left: 0; bottom: 0;
                    z-index: 100;
                    box-shadow: 4px 0 24px rgba(0,0,0,0.2);
                }
                .sidebar-logo {
                    padding: 28px 24px 20px;
                    border-bottom: 1px solid var(--border);
                }
                .sidebar-logo h1 { font-size: 22px; font-weight: 700; color: #fff; letter-spacing: -0.5px; }
                .sidebar-logo h1 span { color: var(--accent); }
                .sidebar-logo p { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
                
                .sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
                .nav-section { padding: 16px 24px 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
                .nav-link {
                    display: flex; align-items: center; gap: 12px;
                    padding: 12px 24px;
                    color: rgba(255,255,255,0.7);
                    text-decoration: none;
                    font-size: 14px; font-weight: 500;
                    transition: all .2s ease;
                    border-left: 3px solid transparent;
                }
                .nav-link:hover { background: rgba(108,99,255,0.08); color: #fff; padding-left: 28px; }
                .nav-link.active { background: rgba(108,99,255,0.15); color: var(--accent2); border-left-color: var(--accent); }
                .nav-link .icon { font-size: 18px; width: 24px; text-align: center; }

                .sidebar-footer { padding: 20px 24px; border-top: 1px solid var(--border); }
                .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
                .user-avatar { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; color: #fff; box-shadow: 0 4px 12px rgba(108,99,255,0.3); }
                .user-name { font-size: 14px; font-weight: 600; color: #fff; }
                .user-role { font-size: 12px; color: var(--text-muted); }
                .logout-btn {
                    display: block; text-align: center;
                    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px;
                    color: #fca5a5; font-size: 13px; font-weight: 500; padding: 10px;
                    text-decoration: none; transition: all .2s;
                }
                .logout-btn:hover { background: rgba(239,68,68,0.2); transform: translateY(-1px); }

                /* ── MAIN ── */
                .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }
                .topbar {
                    background: rgba(10, 11, 16, 0.8);
                    backdrop-filter: var(--glass-blur);
                    -webkit-backdrop-filter: var(--glass-blur);
                    border-bottom: 1px solid var(--border);
                    padding: 16px 40px;
                    display: flex; align-items: center; justify-content: space-between;
                    position: sticky; top: 0; z-index: 50;
                }
                .topbar-title { font-size: 18px; font-weight: 600; color: #fff; }
                .topbar-actions { display: flex; gap: 12px; }
                
                .btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all .2s ease; border: none; }
                .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 4px 12px rgba(108,99,255,0.25); }
                .btn-primary:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(108,99,255,0.35); }
                .btn-ghost { background: rgba(255,255,255,0.05); color: var(--text); border: 1px solid var(--border); }
                .btn-ghost:hover { background: rgba(255,255,255,0.1); transform: translateY(-1px); }
                .btn-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
                .btn-danger:hover { background: rgba(239,68,68,0.2); }

                .content { padding: 40px; max-width: 1400px; width: 100%; margin: 0 auto; }
                
                /* ── CARDS (Glass) ── */
                .card {
                    background: var(--bg-card);
                    backdrop-filter: var(--glass-blur);
                    -webkit-backdrop-filter: var(--glass-blur);
                    border: 1px solid var(--border);
                    border-radius: 16px;
                    overflow: hidden;
                    margin-bottom: 24px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                }
                .card-header { padding: 20px 28px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
                .card-title { font-size: 16px; font-weight: 600; color: #fff; }
                .card-body { padding: 28px; }
                
                /* FORMS */
                .form-group { margin-bottom: 20px; }
                .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-muted); }
                .form-control { width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 10px; color: #fff; font-size: 14px; font-family: 'Inter', sans-serif; transition: border-color .2s; }
                .form-control:focus { outline: none; border-color: var(--accent); background: rgba(0,0,0,0.3); }
                
                /* TABLES */
                .table-responsive { width: 100%; overflow-x: auto; }
                table { width: 100%; border-collapse: collapse; }
                th { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); padding: 16px 28px; text-align: left; border-bottom: 1px solid var(--border); }
                td { padding: 16px 28px; border-bottom: 1px solid var(--border); font-size: 14px; color: rgba(255,255,255,0.85); }
                tr:last-child td { border-bottom: none; }
                tr:hover td { background: rgba(255,255,255,0.03); }
                td a { color: var(--accent2); text-decoration: none; font-weight: 500; transition: color .2s; }
                td a:hover { color: #fff; }
                
                /* UTILS */
                .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; letter-spacing: 0.3px; }
                .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
                .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
                .badge-info    { background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.2); }
                .badge-gray    { background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.1); }
                
                /* ── MODAL ── */
                .modal-overlay {
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
                    display: none; align-items: center; justify-content: center; z-index: 1000;
                    opacity: 0; transition: opacity 0.2s ease;
                }
                .modal-overlay.show { display: flex; opacity: 1; }
                .modal {
                    background: var(--bg-sidebar); border: 1px solid var(--border);
                    border-radius: 16px; width: 100%; max-width: 400px;
                    padding: 24px; box-shadow: 0 12px 40px rgba(0,0,0,0.4);
                    transform: scale(0.95); transition: transform 0.2s ease;
                }
                .modal-overlay.show .modal { transform: scale(1); }
                .modal-title { font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 8px; }
                .modal-body { font-size: 14px; color: var(--text-muted); margin-bottom: 24px; line-height: 1.5; }
                .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
                
                /* ── TOAST NOTIFICATIONS ── */
                .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 2000; display: flex; flex-direction: column; gap: 12px; }
                .toast {
                    background: var(--bg-sidebar); backdrop-filter: var(--glass-blur); border: 1px solid var(--border);
                    padding: 16px 20px; border-radius: 12px; color: #fff; font-size: 14px; font-weight: 500;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 12px;
                    animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .toast.hide { animation: slideOut 0.3s forwards; }
                @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
            </style>
        </head>
        <body>
            <!-- GLOBAL CONFIRM MODAL -->
            <div class="modal-overlay" id="globalConfirmModal">
                <div class="modal">
                    <div class="modal-title" id="confirmModalTitle">Confirmation</div>
                    <div class="modal-body" id="confirmModalBody">Are you sure you want to continue?</div>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" id="confirmModalCancel">Cancel</button>
                        <button class="btn btn-danger" id="confirmModalOk">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="sidebar">
                <div class="sidebar-logo">
                    <h1>Tusk<span>Press</span></h1>
                    <p>{$siteName}</p>
                </div>
                <nav class="sidebar-nav">
                    <div class="nav-section">Main</div>
                    <a href="/wp-admin/" class="nav-link" id="nav-dashboard">
                        <span class="icon">🏠</span> Dashboard
                    </a>
                    <div class="nav-section">Content</div>
                    <a href="/wp-admin/edit" class="nav-link" id="nav-posts">
                        <span class="icon">📝</span> Posts
                    </a>
                    <a href="/wp-admin/post-new" class="nav-link">
                        <span class="icon">➕</span> New Post
                    </a>
                    <a href="/wp-admin/edit?post_type=page" class="nav-link" id="nav-pages">
                        <span class="icon">📄</span> Pages
                    </a>
                    <a href="/wp-admin/upload" class="nav-link" id="nav-media">
                        <span class="icon">🖼️</span> Media
                    </a>
                    <a href="/wp-admin/edit-comments" class="nav-link" id="nav-comments">
                        <span class="icon">💬</span> Comments
                    </a>
                    <div class="nav-section">Appearance</div>
                    <a href="/wp-admin/themes" class="nav-link" id="nav-themes">
                        <span class="icon">🎨</span> Themes
                    </a>
                    <a href="/wp-admin/plugins" class="nav-link" id="nav-plugins">
                        <span class="icon">🔌</span> Plugins
                    </a>
                    <div class="nav-section">Settings</div>
                    <a href="/wp-admin/users" class="nav-link" id="nav-users">
                        <span class="icon">👥</span> Users
                    </a>
                    <a href="/wp-admin/options-general" class="nav-link" id="nav-settings">
                        <span class="icon">⚙️</span> Settings
                    </a>
                </nav>
                <div class="sidebar-footer">
                    <div class="user-info">
                        <div class="user-avatar">{$initials}</div>
                        <div>
                            <div class="user-name">{$username}</div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                    <a href="/wp-admin/logout" class="logout-btn">← Logout</a>
                </div>
            </aside>

            <!-- MAIN -->
            <main class="main">
                <div class="topbar">
                    <div class="topbar-title">{$title}</div>
                    <div class="topbar-actions">
                        <a href="/wp-admin/post-new" class="btn btn-primary">+ New Post</a>
                        <a href="/" class="btn btn-ghost" target="_blank">View Site ↗</a>
                    </div>
                </div>

                <div class="content">
                    {$contentHtml}
                </div>
            </main>

            <script>
                // Hightlight active menu
                document.addEventListener('DOMContentLoaded', () => {
                    const path = window.location.pathname;
                    const search = window.location.search;
                    
                    const mappings = {
                        '/wp-admin/': 'nav-dashboard',
                        '/wp-admin/index': 'nav-dashboard',
                        '/wp-admin/edit': search.includes('post_type=page') ? 'nav-pages' : 'nav-posts',
                        '/wp-admin/upload': 'nav-media',
                        '/wp-admin/edit-comments': 'nav-comments',
                        '/wp-admin/themes': 'nav-themes',
                        '/wp-admin/plugins': 'nav-plugins',
                        '/wp-admin/users': 'nav-users',
                        '/wp-admin/options-general': 'nav-settings',
                    };

                    const activeId = mappings[path] || mappings[path + search];
                    if (activeId) {
                        const el = document.getElementById(activeId);
                        if(el) el.classList.add('active');
                    }
                });

                // Custom Confirm Modal Logic
                window.TuskConfirm = function(message, title, onConfirm) {
                    const modal = document.getElementById('globalConfirmModal');
                    document.getElementById('confirmModalBody').innerText = message || 'Are you sure you want to continue?';
                    document.getElementById('confirmModalTitle').innerText = title || 'Attention';
                    
                    const btnCancel = document.getElementById('confirmModalCancel');
                    const btnOk = document.getElementById('confirmModalOk');
                    
                    const closeModal = () => {
                        modal.classList.remove('show');
                        setTimeout(() => modal.style.display = 'none', 200);
                        btnCancel.onclick = null;
                        btnOk.onclick = null;
                    };
                    
                    modal.style.display = 'flex';
                    setTimeout(() => modal.classList.add('show'), 10);
                    
                    btnCancel.onclick = closeModal;
                    btnOk.onclick = () => {
                        closeModal();
                        if (onConfirm) onConfirm();
                    };
                };

                // Override form submits that have data-confirm attribute
                document.addEventListener('submit', (e) => {
                    const confirmMsg = e.target.getAttribute('data-confirm');
                    if (confirmMsg && !e.target.dataset.confirmed) {
                        e.preventDefault();
                        window.TuskConfirm(confirmMsg, 'Attention', () => {
                            e.target.dataset.confirmed = 'true';
                            e.target.submit();
                        });
                    }
                });

                // Override link clicks that have data-confirm attribute
                document.addEventListener('click', (e) => {
                    const el = e.target.closest('a[data-confirm]');
                    if (el) {
                        e.preventDefault();
                        window.TuskConfirm(el.getAttribute('data-confirm'), 'Attention', () => {
                            window.location.href = el.href;
                        });
                    }
                });

                // Toast Notifications
                window.TuskToast = function(message) {
                    if (!window.toastContainer) {
                        window.toastContainer = document.createElement('div');
                        window.toastContainer.className = 'toast-container';
                        document.body.appendChild(window.toastContainer);
                    }
                    const toast = document.createElement('div');
                    toast.className = 'toast';
                    toast.innerHTML = '<span>🚀</span> ' + message;
                    window.toastContainer.appendChild(toast);
                    setTimeout(() => {
                        toast.classList.add('hide');
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                };
            </script>
        </body>
        </html>
        HTML;
    }

    private static function initial(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials ?: 'U';
    }
}
