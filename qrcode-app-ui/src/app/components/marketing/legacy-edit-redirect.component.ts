import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';

/** Redirects /edit/:id → /app/edit/:id for bookmarks after /app prefix migration. */
@Component({
  selector: 'app-legacy-edit-redirect',
  standalone: true,
  template: '',
})
export class LegacyEditRedirectComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      void this.router.navigate(['/app/edit', id], { replaceUrl: true });
    } else {
      void this.router.navigate(['/app/contacts'], { replaceUrl: true });
    }
  }
}
