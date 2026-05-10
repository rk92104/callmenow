import { Injectable, signal, computed } from '@angular/core';

const STORAGE_KEY = 'cmn-theme';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  /** `true` when dark mode is active. Default is light (`false`). */
  readonly isDark = signal(false);

  readonly themeIcon = computed(() => (this.isDark() ? '☀️' : '🌙'));

  readonly themeLabel = computed(() =>
    this.isDark() ? 'Switch to light mode' : 'Switch to dark mode'
  );

  constructor() {
    this.applyFromStorage();
  }

  private applyFromStorage(): void {
    const stored = localStorage.getItem(STORAGE_KEY);
    const dark = stored === 'dark';
    this.setDark(dark, false);
  }

  /** Persist and set `data-theme` on `<html>`. */
  setDark(dark: boolean, persist = true): void {
    this.isDark.set(dark);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    if (persist) {
      localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
    }
  }

  toggle(): void {
    this.setDark(!this.isDark());
  }
}
