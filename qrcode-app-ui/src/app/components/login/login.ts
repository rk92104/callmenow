import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ThemeService } from '../../services/theme.service';

@Component({
    selector: 'app-login',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterLink],
    templateUrl: './login.html',
    styleUrl: './login.css'
})
export class LoginComponent {
    username = '';
    password = '';
    error = signal<string | null>(null);
    loading = signal(false);

    constructor(
        private readonly authService: AuthService,
        private readonly router: Router,
        readonly theme: ThemeService
    ) { }

    login(): void {
        this.loading.set(true);
        this.error.set(null);

        // Simulate network delay for a premium feel
        setTimeout(() => {
            const success = this.authService.login(this.username, this.password);
            if (success) {
                this.router.navigate(['/app/dashboard']);
            } else {
                this.error.set('Invalid username or password');
                this.loading.set(false);
            }
        }, 800);
    }
}
