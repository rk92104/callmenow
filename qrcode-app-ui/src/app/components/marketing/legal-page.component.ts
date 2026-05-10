import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';

@Component({
  selector: 'app-legal-page',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './legal-page.component.html',
})
export class LegalPageComponent {
  private readonly route = inject(ActivatedRoute);

  readonly doc = toSignal(this.route.data.pipe(map((d) => String(d['doc'] ?? 'privacy'))), {
    initialValue: 'privacy',
  });
}
