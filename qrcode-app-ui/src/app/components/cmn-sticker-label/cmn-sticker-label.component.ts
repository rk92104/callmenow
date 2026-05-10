import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';

export type CmnStickerVariant = 'scan' | 'activate';
export type CmnStickerLayout = 'horizontal' | 'vertical';

@Component({
  selector: 'app-cmn-sticker-label',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cmn-sticker-label.component.html',
  styleUrl: './cmn-sticker-label.component.css',
})
export class CmnStickerLabelComponent {
  readonly qrImageUrl = input.required<string>();
  readonly code = input.required<string>();
  /** `scan` = vehicle sticker copy; `activate` = packaging copy. */
  readonly variant = input<CmnStickerVariant>('scan');
  /** Sticker shape for screen + print (inventory preview). */
  readonly layout = input<CmnStickerLayout>('horizontal');
  /** Brand mark — default SVG asset; override with another URL or `data:image/svg+xml,...` if needed. */
  readonly logoUrl = input<string>('assets/marketing/callmenow-logo.png');
}
