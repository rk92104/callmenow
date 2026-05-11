import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, switchMap, of, catchError } from 'rxjs';
import {
  CmnStickerLabelComponent,
  CmnStickerLayout,
  CmnStickerVariant,
} from '../cmn-sticker-label/cmn-sticker-label.component';
import { formatStickerDisplayCode } from '../cmn-sticker-label/sticker-label.utils';
import { QrService } from '../../services/qr.service';

@Component({
  selector: 'app-label-print-preview',
  standalone: true,
  imports: [CommonModule, RouterLink, CmnStickerLabelComponent],
  templateUrl: './label-print-preview.component.html',
  styleUrl: './label-print-preview.component.css',
})
export class LabelPrintPreviewComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly qrService = inject(QrService);

  /** Screen + `@page` orientation for browser print. */
  readonly printLayout = signal<CmnStickerLayout>(
    ['vertical', 'v', 'portrait'].includes(
      (this.route.snapshot.queryParamMap.get('layout') ?? '').toLowerCase()
    )
      ? 'vertical'
      : 'horizontal'
  );

  setPrintLayout(layout: CmnStickerLayout): void {
    this.printLayout.set(layout);
  }

  readonly publicId = toSignal(
    this.route.paramMap.pipe(map((p) => decodeURIComponent(p.get('publicId') ?? '').trim())),
    { initialValue: '' }
  );

  readonly embed = toSignal(
    this.route.queryParamMap.pipe(
      map((q): CmnStickerVariant => ((q.get('embed') ?? 'activate').toLowerCase() === 'scan' ? 'scan' : 'activate'))
    ),
    { initialValue: 'activate' as CmnStickerVariant }
  );

  readonly isFamilyPack = toSignal(
    this.route.paramMap.pipe(
      map((p) => decodeURIComponent(p.get('publicId') ?? '').trim()),
      switchMap(id => this.qrService.scan(id).pipe(catchError(() => of(null)))),
      map(res => {
         const t = res?.productType?.toLowerCase() || '';
         return t === 'family' || t === 'familypack' || t === 'family pack';
      })
    ),
    { initialValue: false }
  );

  readonly qrImageUrl = computed(() => {
    const id = encodeURIComponent(this.publicId());
    const e = this.embed();
    return `/api/qr/${id}/image?embed=${e}&modulePixels=36`;
  });

  readonly code = computed(() => formatStickerDisplayCode(this.publicId()));

  apiPrintHref(): string {
    return this.qrService.printLabelHref(this.publicId(), this.embed());
  }

  printPreview(): void {
    window.print();
  }
}
