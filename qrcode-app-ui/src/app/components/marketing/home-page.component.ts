import { isPlatformBrowser, DOCUMENT } from '@angular/common';
import { Component, OnDestroy, OnInit, PLATFORM_ID, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface HeroSlide {
  src: string;
  alt: string;
}

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './home-page.component.html',
})
export class HomePageComponent implements OnInit, OnDestroy {
  private readonly platformId = inject(PLATFORM_ID);
  private readonly document = inject(DOCUMENT);

  /** Root paths (`/marketing/...`, `/assets/...`) → full URL for correct origin (Kestrel / ng serve). */
  assetUrl(path: string): string {
    if (!isPlatformBrowser(this.platformId)) return path;
    const origin = this.document.defaultView?.location?.origin;
    if (!origin) return path;
    try {
      return new URL(path, origin).href;
    } catch {
      return path;
    }
  }

  /** Files live under `public/marketing/` → served as `/marketing/...` after build. */
  readonly slides: HeroSlide[] = [
    {
      src: '/marketing/hero-vehicle-qr.png',
      alt: 'Car windshield with CallMeNow QR sticker — scan to reach the owner privately',
    },
    {
      src: '/marketing/hero-1.png',
      alt: 'Vehicle with QR sticker — daylight, clear visibility',
    },
    {
      src: '/marketing/sticker-hero.png',
      alt: 'CallMeNow sticker on the car — real-world look',
    },
    {
      src: '/marketing/hero-slide-03.png',
      alt: 'Applying a laminated QR sticker to vehicle glass',
    },
    {
      src: '/marketing/hero-5.png',
      alt: 'Scanning the vehicle QR from outside the car',
    },
  ];

  readonly activeIndex = signal(0);

  private intervalId?: ReturnType<typeof setInterval>;
  private readonly intervalMs = 5000;

  ngOnInit(): void {
    if (!this.prefersReducedMotion()) {
      this.startAutoplay();
    }
  }

  private prefersReducedMotion(): boolean {
    if (typeof matchMedia === 'undefined') return false;
    return matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  ngOnDestroy(): void {
    this.clearAutoplay();
  }

  goToSlide(i: number): void {
    const n = this.slides.length;
    if (n === 0) return;
    const idx = ((i % n) + n) % n;
    this.activeIndex.set(idx);
    this.startAutoplay();
  }

  prev(): void {
    this.goToSlide(this.activeIndex() - 1);
  }

  next(): void {
    this.goToSlide(this.activeIndex() + 1);
  }

  private startAutoplay(): void {
    this.clearAutoplay();
    this.intervalId = setInterval(() => {
      this.activeIndex.update((i) => (i + 1) % this.slides.length);
    }, this.intervalMs);
  }

  private clearAutoplay(): void {
    if (this.intervalId !== undefined) {
      clearInterval(this.intervalId);
      this.intervalId = undefined;
    }
  }
}
