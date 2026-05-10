import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ThemeService } from '../../services/theme.service';

@Component({
  selector: 'app-main-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="app-container">
      <aside class="sidebar" aria-label="Main navigation">
        <div class="sidebar-rail" aria-hidden="true"></div>
        <div class="sidebar-inner">
          <a routerLink="/app/dashboard" class="brand-block" aria-label="CallmeNow, merchant console">
            <img
              src="/assets/marketing/callmenow-logo.png"
              alt=""
              class="brand-logo-img"
            />
            <div class="brand-text">
              <span class="brand-tag">Merchant console</span>
            </div>
          </a>

          <p class="nav-section-label">Menu</p>
          <nav class="sidebar-nav">
            <a
              routerLink="/app/dashboard"
              routerLinkActive="active"
              [routerLinkActiveOptions]="{ exact: true }"
              class="nav-item"
            >
              <span class="nav-icon-wrap" aria-hidden="true">
                <svg class="nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 19V9l8-5 8 5v10M9 19v-6h6v6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="nav-label">Dashboard</span>
            </a>
            <a routerLink="/app/inventory" routerLinkActive="active" class="nav-item">
              <span class="nav-icon-wrap" aria-hidden="true">
                <svg class="nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 7h4v10H4V7zm6-4h4v14h-4V3zm6 6h4v8h-4V9z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="nav-label">QR inventory</span>
            </a>
            <a routerLink="/app/contacts" class="nav-item" [class.active]="ownersSectionActive()">
              <span class="nav-icon-wrap" aria-hidden="true">
                <svg class="nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke-linecap="round" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" />
                </svg>
              </span>
              <span class="nav-label">Owners</span>
            </a>
          </nav>

          <div class="sidebar-footer">
            <div class="footer-card">
              <span class="footer-card-title">CallmeNow</span>
              <span class="footer-card-text">Inventory · activation · directory</span>
            </div>
          </div>
        </div>
      </aside>

      <div class="content-wrap">
        <div class="main-surface">
          <header class="top-bar">
            <div class="top-bar-spacer"></div>
            <div class="top-bar-actions">
              <button
                type="button"
                class="icon-btn theme-toggle"
                (click)="theme.toggle()"
                [attr.aria-label]="theme.themeLabel()"
                [title]="theme.themeLabel()"
              >
                <span class="icon-btn-emoji" aria-hidden="true">{{ theme.themeIcon() }}</span>
              </button>
              <div class="user-avatar" title="Account" aria-hidden="true">
                <svg class="user-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                  />
                </svg>
              </div>
              <button type="button" class="btn-logout-top" (click)="logout()">Logout</button>
            </div>
          </header>

          <main class="main-content">
            <router-outlet></router-outlet>
          </main>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      :host {
        display: block;
        min-height: 100vh;
        /* Sidebar total width + fixed header height (must stay in sync below) */
        --shell-sidebar-w: 252px;
        --shell-topbar-h: 56px;
      }

      .app-container {
        display: flex;
        min-height: 100vh;
        background: var(--cmn-page-bg);
        color: var(--cmn-text);
      }

      .sidebar {
        width: var(--shell-sidebar-w);
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        height: 100dvh;
        z-index: 1000;
        display: flex;
        background: var(--cmn-sidebar-bg);
        border-right: 1px solid var(--cmn-sidebar-border);
        box-shadow: var(--cmn-sidebar-shadow);
        backdrop-filter: blur(24px);
      }

      .sidebar-rail {
        width: 3px;
        flex-shrink: 0;
        background: var(--cmn-shell-rail);
        box-shadow: 0 0 20px rgba(99, 102, 241, 0.35);
      }

      html[data-theme='dark'] .sidebar-rail {
        box-shadow: 0 0 28px rgba(129, 140, 248, 0.25);
      }

      .sidebar-inner {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        padding: 0 8px 16px;
        background: linear-gradient(
          165deg,
          var(--cmn-sidebar-highlight) 0%,
          transparent 42%
        );
      }

      .brand-block {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 6px 14px;
        text-decoration: none;
        color: inherit;
        border-radius: 14px;
        margin: 0 0 4px;
        transition: background 0.2s ease;
      }

      .brand-block:hover {
        background: rgba(99, 102, 241, 0.06);
      }

      html[data-theme='dark'] .brand-block:hover {
        background: rgba(99, 102, 241, 0.1);
      }

      .brand-logo-img {
        display: block;
        flex-shrink: 0;
        max-height: 48px;
        max-width: min(240px, 100%);
        width: auto;
        height: auto;
        object-fit: contain;
        background: transparent;
        padding: 4px 6px;
        border-radius: 11px;
        box-sizing: content-box;
        box-shadow: none;
      }

      .brand-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
      }

      .brand-tag {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--cmn-text-muted);
        letter-spacing: 0.02em;
        text-transform: uppercase;
      }

      .nav-section-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--cmn-text-muted);
        padding: 4px 8px 8px;
        margin: 0;
        opacity: 0.85;
      }

      .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 0;
      }

      .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 14px;
        color: var(--cmn-nav);
        font-weight: 600;
        font-size: 0.94rem;
        text-decoration: none;
        transition:
          background 0.2s ease,
          color 0.2s ease,
          box-shadow 0.2s ease,
          transform 0.15s ease;
        position: relative;
      }

      .nav-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%) scaleY(0);
        width: 3px;
        height: 0;
        border-radius: 0 4px 4px 0;
        background: var(--cmn-nav-active-border);
        transition:
          height 0.2s ease,
          transform 0.2s ease;
      }

      .nav-item:hover {
        background: rgba(99, 102, 241, 0.08);
        color: var(--cmn-nav-hover);
      }

      html[data-theme='dark'] .nav-item:hover {
        background: rgba(99, 102, 241, 0.12);
      }

      .nav-item.active {
        background: var(--cmn-nav-active-bg);
        color: var(--cmn-nav-active);
        box-shadow:
          inset 0 0 0 1px rgba(99, 102, 241, 0.2),
          0 4px 14px rgba(99, 102, 241, 0.12);
      }

      html[data-theme='dark'] .nav-item.active {
        box-shadow:
          inset 0 0 0 1px rgba(129, 140, 248, 0.25),
          0 6px 20px rgba(0, 0, 0, 0.2);
      }

      .nav-item.active::before {
        height: 60%;
        transform: translateY(-50%) scaleY(1);
      }

      .nav-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(99, 102, 241, 0.08);
        color: var(--cmn-nav);
        transition: inherit;
      }

      .nav-item:hover .nav-icon-wrap {
        background: rgba(99, 102, 241, 0.14);
        color: var(--cmn-nav-hover);
      }

      .nav-item.active .nav-icon-wrap {
        background: rgba(99, 102, 241, 0.2);
        color: var(--cmn-nav-active);
      }

      html[data-theme='dark'] .nav-icon-wrap {
        background: rgba(129, 140, 248, 0.1);
      }

      html[data-theme='dark'] .nav-item.active .nav-icon-wrap {
        background: rgba(129, 140, 248, 0.22);
      }

      .nav-svg {
        width: 20px;
        height: 20px;
      }

      .nav-label {
        flex: 1;
      }

      .sidebar-footer {
        margin-top: auto;
        padding: 8px 0 0;
      }

      .footer-card {
        border-radius: 12px;
        padding: 12px 12px;
        background: rgba(99, 102, 241, 0.07);
        border: 1px solid rgba(99, 102, 241, 0.12);
      }

      html[data-theme='dark'] .footer-card {
        background: rgba(30, 30, 55, 0.6);
        border-color: rgba(129, 140, 248, 0.15);
      }

      .footer-card-title {
        display: block;
        font-size: 0.8rem;
        font-weight: 800;
        color: var(--cmn-heading);
        margin-bottom: 4px;
      }

      .footer-card-text {
        font-size: 0.72rem;
        color: var(--cmn-text-muted);
        line-height: 1.4;
      }

      .content-wrap {
        flex: 1;
        margin-left: var(--shell-sidebar-w);
        min-width: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }

      .main-surface {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        min-height: 100dvh;
        background: var(--cmn-main-glow);
        /* Space for fixed header (+ notch) */
        padding-top: calc(var(--shell-topbar-h) + env(safe-area-inset-top, 0px));
      }

      .top-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        height: var(--shell-topbar-h);
        min-height: var(--shell-topbar-h);
        padding: 0 clamp(12px, 3vw, 24px);
        box-sizing: border-box;
        border-bottom: 1px solid var(--cmn-sidebar-border);
        background: var(--cmn-topbar-bg);
        backdrop-filter: blur(16px);
        box-shadow: var(--cmn-topbar-shadow);
        position: fixed;
        top: env(safe-area-inset-top, 0px);
        left: var(--shell-sidebar-w);
        right: 0;
        z-index: 900;
      }

      .top-bar-spacer {
        flex: 1;
      }

      .top-bar-actions {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        border: 1px solid var(--cmn-card-border);
        background: var(--cmn-card-bg-solid);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition:
          background 0.2s ease,
          border-color 0.2s ease,
          transform 0.15s ease;
      }

      html[data-theme='dark'] .icon-btn {
        background: rgba(30, 30, 50, 0.88);
      }

      .icon-btn:hover {
        border-color: rgba(99, 102, 241, 0.45);
        background: rgba(99, 102, 241, 0.1);
        transform: translateY(-1px);
      }

      .icon-btn-emoji {
        font-size: 1.15rem;
        line-height: 1;
      }

      .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--cmn-user-avatar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4f46e5;
        border: 1px solid var(--cmn-card-border);
        box-shadow: 0 2px 12px rgba(99, 102, 241, 0.15);
      }

      html[data-theme='dark'] .user-avatar {
        color: #fff;
        border-color: rgba(129, 140, 248, 0.3);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
      }

      .user-svg {
        width: 22px;
        height: 22px;
      }

      .btn-logout-top {
        padding: 8px 16px;
        border-radius: 12px;
        border: 1px solid rgba(239, 68, 68, 0.22);
        background: rgba(239, 68, 68, 0.06);
        color: #dc2626;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition:
          background 0.2s ease,
          border-color 0.2s ease,
          transform 0.15s ease;
      }

      html[data-theme='dark'] .btn-logout-top {
        color: #f87171;
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.25);
      }

      .btn-logout-top:hover {
        background: rgba(239, 68, 68, 0.14);
        border-color: rgba(239, 68, 68, 0.4);
        transform: translateY(-1px);
      }

      .main-content {
        flex: 1;
        padding: clamp(14px, 3vw, 28px) clamp(14px, 4vw, 40px) clamp(28px, 5vw, 56px);
        padding-bottom: max(clamp(28px, 5vw, 56px), env(safe-area-inset-bottom, 0px));
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
      }

      @media (max-width: 900px) {
        :host {
          --shell-sidebar-w: 76px;
          --shell-topbar-h: 52px;
        }

        .sidebar {
          width: var(--shell-sidebar-w);
        }

        .sidebar-rail {
          width: 3px;
        }

        .sidebar-inner {
          padding: 0 6px 12px;
        }

        .brand-text,
        .nav-section-label,
        .nav-label,
        .sidebar-footer {
          display: none;
        }

        .brand-block {
          justify-content: center;
          padding: 18px 8px;
        }

        .brand-logo-img {
          margin: 0;
          max-height: 44px;
          max-width: 100%;
          padding: 5px 6px;
        }

        .content-wrap {
          margin-left: var(--shell-sidebar-w);
        }

        .nav-item {
          justify-content: center;
          padding: 12px;
        }

        .nav-icon-wrap {
          margin: 0;
        }

        .main-content {
          padding: 14px clamp(10px, 3vw, 16px) max(32px, env(safe-area-inset-bottom, 0px));
        }
      }

      @media (max-width: 480px) {
        :host {
          --shell-topbar-h: 50px;
          --shell-sidebar-w: 64px;
        }

        .nav-icon-wrap {
          width: 36px;
          height: 36px;
          border-radius: 11px;
        }

        .nav-svg {
          width: 18px;
          height: 18px;
        }

        .top-bar-actions {
          gap: 6px;
        }

        .btn-logout-top {
          padding: 8px 12px;
          font-size: 0.85rem;
        }
      }

      /* Phone layout: convert left sidebar into bottom nav (icons-only). */
      @media (max-width: 600px) {
        :host {
          --shell-sidebar-w: 0px;
          --shell-bottomnav-h: 64px;
        }

        .sidebar {
          width: 100%;
          height: calc(var(--shell-bottomnav-h) + env(safe-area-inset-bottom, 0px));
          top: auto;
          bottom: 0;
          left: 0;
          right: 0;
          border-right: none;
          border-top: 1px solid var(--cmn-sidebar-border);
          backdrop-filter: blur(18px);
        }

        .sidebar-rail {
          display: none;
        }

        .sidebar-inner {
          flex-direction: row;
          align-items: center;
          justify-content: center;
          padding: 6px 8px;
          background: transparent;
        }

        .brand-block,
        .sidebar-footer,
        .nav-section-label {
          display: none;
        }

        .sidebar-nav {
          flex: 1;
          flex-direction: row;
          justify-content: space-around;
          align-items: center;
          gap: 6px;
          padding: 0;
        }

        .nav-item {
          flex: 1;
          justify-content: center;
          padding: 10px 6px;
        }

        .nav-icon-wrap {
          width: 42px;
          height: 42px;
          border-radius: 14px;
        }

        .content-wrap {
          margin-left: 0;
        }

        .top-bar {
          left: 0;
        }

        .main-surface {
          padding-bottom: calc(var(--shell-bottomnav-h) + env(safe-area-inset-bottom, 0px));
        }
      }

      @media (max-width: 360px) {
        .user-avatar {
          display: none;
        }

        .btn-logout-top {
          padding: 7px 10px;
          font-size: 0.78rem;
        }

        .icon-btn {
          width: 38px;
          height: 38px;
        }
      }
    `,
  ],
})
export class MainLayoutComponent {
  constructor(
    readonly theme: ThemeService,
    private readonly authService: AuthService,
    private readonly router: Router
  ) {}

  /** Highlight Owners for directory, add, and edit routes. */
  ownersSectionActive(): boolean {
    const path = this.router.url.split('?')[0];
    return (
      path.startsWith('/app/contacts') ||
      path === '/app/add' ||
      /^\/app\/edit\/\d+/.test(path)
    );
  }

  logout(): void {
    this.authService.logout();
  }
}
