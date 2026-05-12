import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { QrService } from '../../services/qr.service';
import { QrScanResponse } from '../../models/qr.model';
import { formatStickerDisplayCode } from '../cmn-sticker-label/sticker-label.utils';

@Component({
  selector: 'app-scan-page',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './scan-page.html',
  styleUrl: './scan-page.css',
})
export class ScanPageComponent implements OnInit {
  private static readonly scannerPhoneStorageKey = 'callmenow_exotel_scanner_v1';

  loading = signal(true);
  notFound = signal(false);
  data = signal<QrScanResponse | null>(null);
  leadPhone = '';
  leadSent = signal(false);
  leadBusy = signal(false);
  leadErr = signal<string | null>(null);
  callDoneHint = signal(false);
  copyToast = signal(false);
  fromPhone = '';
  /** When true, saved number is used and the phone field is hidden (one-tap Exotel). */
  exotelCompactUi = signal(false);
  rememberScannerPhone = true;
  connectBusy = signal(false);
  connectFeedback = signal<string | null>(null);
  /** When true, connect feedback is shown in error styling (e.g. Exotel / 502). */
  connectFeedbackIsError = signal(false);

  constructor(
    private readonly route: ActivatedRoute,
    private readonly qrService: QrService
  ) {}

  ngOnInit(): void {
    const raw = this.route.snapshot.paramMap.get('publicId') ?? '';
    const publicId = decodeURIComponent(raw);
    if (!publicId) {
      this.notFound.set(true);
      this.loading.set(false);
      return;
    }
    this.qrService.scan(publicId).subscribe({
      next: (res) => {
        this.data.set(res);
        if (res.ownerConnectViaExotel && !res.exotelOwnerOnlyAlert) {
          const saved = this.readSavedScannerPhone();
          if (saved) {
            this.fromPhone = saved;
            this.exotelCompactUi.set(true);
          } else {
            this.fromPhone = '';
            this.exotelCompactUi.set(false);
          }
        }
        this.loading.set(false);
      },
      error: () => {
        this.notFound.set(true);
        this.loading.set(false);
      },
    });
  }

  submitLead(): void {
    const phone = this.leadPhone.trim();
    if (phone.length < 8) return;
    const d = this.data();
    this.leadErr.set(null);
    this.leadBusy.set(true);
    this.qrService
      .submitMarketingLead(phone, d?.publicId ?? '', d?.referralCode ?? '')
      .subscribe({
        next: () => {
          this.leadSent.set(true);
          this.leadBusy.set(false);
        },
        error: () => {
          this.leadErr.set('Could not save — check the API.');
          this.leadBusy.set(false);
        },
      });
  }

  onCallClick(): void {
    this.callDoneHint.set(true);
  }

  connectOwnerMasked(): void {
    const d = this.data();
    if (!d?.publicId || !d.ownerConnectViaExotel) return;
    if (!d.exotelOwnerOnlyAlert) {
      const digits = this.fromPhone.replace(/\D/g, '');
      if (digits.length < 10) {
        this.connectFeedbackIsError.set(true);
        this.connectFeedback.set('Please enter your 10-digit mobile number to proceed.');
        return;
      }
    }
    this.connectBusy.set(true);
    this.connectFeedbackIsError.set(false);
    // Exotel PSTN starts only after HTTP + carrier — show line immediately so it doesn’t feel “dead”
    this.connectFeedback.set(
      d.exotelOwnerOnlyAlert
        ? 'Placing call to the owner via Exotel…'
        : 'Request sent to Exotel. Your phone may ring in a few seconds while the network connects.'
    );
    const phoneArg = d.exotelOwnerOnlyAlert ? undefined : this.fromPhone;
    this.qrService.exotelConnectOwner(d.publicId, phoneArg).subscribe({
      next: (r) => {
        this.connectBusy.set(false);
        this.connectFeedbackIsError.set(false);
        this.connectFeedback.set(
          r.message ?? (d.exotelOwnerOnlyAlert ? 'Owner call started.' : 'You should receive a call shortly.')
        );
        if (!d.exotelOwnerOnlyAlert && this.rememberScannerPhone) {
          this.saveScannerPhone(this.fromPhone);
          this.exotelCompactUi.set(true);
        }
        this.onCallClick();
      },
      error: (err) => {
        this.connectBusy.set(false);
        this.connectFeedbackIsError.set(true);
        this.connectFeedback.set(this.parseConnectErrorMessage(err));
      },
    });
  }

  connectEmergencyMasked(): void {
    const d = this.data();
    if (!d?.publicId || !d.ownerConnectViaExotel) return;
    
    const digits = this.fromPhone.replace(/\D/g, '');
    if (digits.length < 10) {
      this.connectFeedbackIsError.set(true);
      this.connectFeedback.set('Please enter your 10-digit mobile number first.');
      return;
    }

    this.connectBusy.set(true);
    this.connectFeedbackIsError.set(false);
    this.connectFeedback.set('Requesting emergency connection via Exotel…');

    this.qrService.exotelConnectOwner(d.publicId, this.fromPhone, true).subscribe({
      next: (r) => {
        this.connectBusy.set(false);
        this.connectFeedbackIsError.set(false);
        this.connectFeedback.set(r.message ?? 'Emergency call started.');
        
        if (this.rememberScannerPhone) {
          this.saveScannerPhone(this.fromPhone);
          this.exotelCompactUi.set(true);
        }
        
        this.onCallClick();
      },
      error: (err) => {
        this.connectBusy.set(false);
        this.connectFeedbackIsError.set(true);
        this.connectFeedback.set(this.parseConnectErrorMessage(err));
      },
    });
  }

  contactPickerSupported(): boolean {
    const n = navigator as Navigator & { contacts?: { select?: unknown } };
    return typeof navigator !== 'undefined' && typeof n.contacts?.select === 'function';
  }

  async pickFromContacts(): Promise<void> {
    const n = navigator as Navigator & {
      contacts?: { select: (props: string[], opts?: { multiple?: boolean }) => Promise<Array<{ tel?: string[] }>> };
    };
    if (!n.contacts?.select) return;
    try {
      const picked = await n.contacts.select(['tel'], { multiple: false });
      const first = picked?.[0]?.tel?.[0];
      if (typeof first === 'string' && first.trim() !== '') {
        this.fromPhone = first.trim();
        this.exotelCompactUi.set(false);
      }
    } catch {
      /* cancelled or unsupported */
    }
  }

  onPhoneInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const cleaned = input.value.replace(/\D/g, '').substring(0, 10);
    this.fromPhone = cleaned;
    input.value = cleaned;
    
    // Clear error state if result is long enough
    if (cleaned.length === 10) {
      this.connectFeedbackIsError.set(false);
      this.connectFeedback.set(null);
    }
  }

  expandExotelPhone(): void {
    this.clearSavedScannerPhone();
    this.fromPhone = '';
    this.exotelCompactUi.set(false);
  }

  private readSavedScannerPhone(): string | null {
    try {
      const raw = localStorage.getItem(ScanPageComponent.scannerPhoneStorageKey);
      if (!raw) return null;
      const digits = raw.replace(/\D/g, '');
      return digits.length >= 10 ? raw : null;
    } catch {
      return null;
    }
  }

  private saveScannerPhone(raw: string): void {
    const digits = raw.replace(/\D/g, '');
    if (digits.length < 10) return;
    try {
      localStorage.setItem(ScanPageComponent.scannerPhoneStorageKey, digits);
    } catch {
      /* private mode */
    }
  }

  private clearSavedScannerPhone(): void {
    try {
      localStorage.removeItem(ScanPageComponent.scannerPhoneStorageKey);
    } catch {
      /* ignore */
    }
  }

  private parseConnectErrorMessage(err: unknown): string {
    const e = err as { error?: { message?: unknown } };
    const m = e?.error?.message;
    if (typeof m === 'string' && m.trim() !== '') {
      return m.trim();
    }
    return 'Could not start the call. Try again.';
  }

  ownerCallLabel(d: QrScanResponse): string {
    const kind = d.primaryPhoneType === 'Landline' ? 'landline' : 'mobile';
    return `Call owner (${kind})`;
  }

  emergencyCallLabel(d: QrScanResponse): string {
    const kind = d.emergencyPhoneType === 'Landline' ? 'landline' : 'mobile';
    return `Emergency (${kind})`;
  }

  shareWhatsApp(): void {
    const d = this.data();
    if (!d) return;
    const text = encodeURIComponent(
      `CallMeNow — contact vehicle owners without sharing your number. ${d.sharePageUrl}`
    );
    window.open(`https://wa.me/?text=${text}`, '_blank');
  }

  shareTelegram(): void {
    const d = this.data();
    if (!d) return;
    const text = encodeURIComponent(
      `CallMeNow — privacy-first QR contact for your vehicle. ${d.sharePageUrl}`
    );
    window.open(`https://t.me/share/url?url=${encodeURIComponent(d.sharePageUrl)}&text=${text}`, '_blank');
  }

  /** Activate URL with optional `?ref=` for referral discount on checkout. */
  activateHref(d: QrScanResponse): string {
    const base = d.activatePageUrl;
    const code = (d.referralCode ?? '').trim();
    if (!code) return base;
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}ref=${encodeURIComponent(code)}`;
  }

  copyLink(): void {
    const d = this.data();
    if (!d?.sharePageUrl) return;
    void navigator.clipboard.writeText(d.sharePageUrl);
    this.copyToast.set(true);
    setTimeout(() => this.copyToast.set(false), 2000);
  }

  shareInstagram(): void {
    const d = this.data();
    if (!d) return;
    const caption = `CallMeNow — contact vehicle owners without sharing your number. ${d.sharePageUrl}`;
    void navigator.clipboard.writeText(caption);
    window.open('https://www.instagram.com/', '_blank', 'noopener,noreferrer');
  }

  /** Vehicle sticker QR (same as print label / inventory API). */
  stickerQrImageUrl(d: QrScanResponse): string {
    const id = encodeURIComponent(d.publicId.trim());
    return `/api/qr/${id}/image?embed=scan&modulePixels=36`;
  }

  /** Label under QR — matches physical sticker style (CMN + id). */
  stickerCodeLabel(d: QrScanResponse): string {
    return formatStickerDisplayCode(d.publicId);
  }
}
