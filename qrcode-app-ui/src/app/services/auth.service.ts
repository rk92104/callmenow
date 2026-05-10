import { Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';

@Injectable({
    providedIn: 'root'
})
export class AuthService {
    private readonly AUTH_KEY = 'qr_app_auth';
    isLoggedIn = signal(this.checkAuth());

    constructor(private readonly router: Router) { }

    login(username: string, password: string): boolean {
        if (username === 'admin' && password === 'Welcome@1234') {
            localStorage.setItem(this.AUTH_KEY, 'true');
            this.isLoggedIn.set(true);
            return true;
        }
        return false;
    }

    logout(): void {
        localStorage.removeItem(this.AUTH_KEY);
        this.isLoggedIn.set(false);
        this.router.navigate(['/login']);
    }

    private checkAuth(): boolean {
        return localStorage.getItem(this.AUTH_KEY) === 'true';
    }
}
