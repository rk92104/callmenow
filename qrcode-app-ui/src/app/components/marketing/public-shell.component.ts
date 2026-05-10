import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-public-shell',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './public-shell.component.html',
})
export class PublicShellComponent {
  readonly menuOpen = signal(false);

  toggleMenu(): void {
    this.menuOpen.update((v) => !v);
  }

  closeMenu(): void {
    this.menuOpen.set(false);
  }

  /** Window + document roots — covers iOS / nested scroll quirks. */
  scrollToTop(): void {
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
  }

  onLogoClick(): void {
    this.closeMenu();
    this.scrollToTop();
  }

  /** Desktop / CTA links: scroll up (also handles same-route clicks). */
  onPublicNavClick(): void {
    this.scrollToTop();
  }

  /** Mobile drawer links: close + scroll. */
  onMobileNavClick(): void {
    this.closeMenu();
    this.scrollToTop();
  }
}
