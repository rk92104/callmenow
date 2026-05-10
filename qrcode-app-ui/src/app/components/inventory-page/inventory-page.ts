import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { QrService } from '../../services/qr.service';
import { QrInventoryItem } from '../../models/qr.model';

@Component({
  selector: 'app-inventory-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './inventory-page.html',
  styleUrl: './inventory-page.css',
})
export class InventoryPageComponent implements OnInit {
  items = signal<QrInventoryItem[]>([]);
  total = signal(0);
  loading = signal(true);
  generating = signal(false);
  genCount = 5;
  genProduct: 'CarSticker' | 'KeyFinder' | 'LuggageTag' = 'CarSticker';
  toast = signal<string | null>(null);

  /** A4 batch print (API max 500) */
  batchCount = 500;
  batchEmbed: 'activate' | 'scan' = 'activate';
  batchLayout: 'horizontal' | 'vertical' = 'horizontal';
  batchPrinting = signal(false);
  deleting = signal(false);
  confirmOpen = signal(false);
  confirmMessage = signal('');
  /** Selected publicIds for manual print. */
  selected = signal<Record<string, boolean>>({});
  selectedLayout: 'horizontal' | 'vertical' = 'horizontal';
  page = 1;
  pageSize = 20;
  readonly pageSizeOptions = [5, 10, 20, 100, 500, 1000, 2000];
  fromDate = '';
  toDate = '';
  searchNumber = '';
  statusFilter: 'all' | 'active' | 'unused' = 'all';
  private pendingDeleteAction: 'selected' | 'all' | null = null;

  activeFreeOpen = signal(false);
  submittingFree = signal(false);
  selectedSticker = signal<QrInventoryItem | null>(null);
  freeFormData = {
    name: '',
    phoneNumber: '',
    phoneNumberType: 'Mobile' as 'Mobile' | 'Landline',
    email: '',
    address: '',
    fatherName: '',
    vehicleRegistration: '',
    emergencyContactPhone: '',
    emergencyContactPhoneType: 'Mobile' as 'Mobile' | 'Landline',
  };

  constructor(private readonly qrService: QrService) {}

  ngOnInit(): void {
    this.setDefaultDateRange();
    this.batchCount = this.pageSize;
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.qrService.listInventory({
      page: this.page,
      pageSize: this.pageSize,
      from: this.fromDate || undefined,
      to: this.toDate || undefined,
      search: this.searchNumber || undefined,
      status: this.statusFilter === 'all' ? undefined : this.statusFilter,
    }).subscribe({
      next: (res) => {
        const rows = res.items ?? [];
        this.items.set(rows);
        this.total.set(res.total ?? 0);
        this.page = Math.max(1, res.page || this.page);
        this.pageSize = res.pageSize || this.pageSize;
        // prune selection for rows no longer present
        const alive = new Set(rows.map((r) => r.publicId));
        const nextSel: Record<string, boolean> = {};
        const cur = this.selected();
        for (const k of Object.keys(cur)) {
          if (cur[k] && alive.has(k)) nextSel[k] = true;
        }
        this.selected.set(nextSel);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.flash(this.apiErrorText(err, 'Could not load inventory. Is the API running?'));
      },
    });
  }

  applyFilters(): void {
    this.page = 1;
    this.reload();
  }

  resetFilters(): void {
    this.setDefaultDateRange();
    this.searchNumber = '';
    this.statusFilter = 'all';
    this.page = 1;
    this.reload();
  }

  onPageSizeChanged(): void {
    this.batchCount = this.pageSize;
    this.page = 1;
    this.reload();
  }

  nextPage(): void {
    if (this.page < this.totalPages()) {
      this.page += 1;
      this.reload();
    }
  }

  prevPage(): void {
    if (this.page > 1) {
      this.page -= 1;
      this.reload();
    }
  }

  totalPages(): number {
    return Math.max(1, Math.ceil(this.total() / this.pageSize));
  }

  generate(): void {
    const n = Math.min(500, Math.max(1, Number(this.genCount) || 1));
    this.genCount = n;
    this.generating.set(true);
    this.qrService.generateInventory(n, this.genProduct).subscribe({
      next: () => {
        this.generating.set(false);
        this.flash(`Generated ${n} sticker(s).`);
        this.reload();
      },
      error: (err) => {
        this.generating.set(false);
        this.flash(this.apiErrorText(err, 'Generate failed.'));
      },
    });
  }

  private flash(msg: string): void {
    this.toast.set(msg);
    setTimeout(() => this.toast.set(null), 3500);
  }

  labelPackHref(row: QrInventoryItem): string {
    return this.qrService.printLabelHref(row.publicId, 'activate');
  }

  labelStickerHref(row: QrInventoryItem): string {
    return this.qrService.printLabelHref(row.publicId, 'scan');
  }

  toggleSelected(publicId: string, on: boolean): void {
    const cur = this.selected();
    const next = { ...cur };
    if (on) next[publicId] = true;
    else delete next[publicId];
    this.selected.set(next);
  }

  clearSelected(): void {
    this.selected.set({});
  }

  selectedCount(): number {
    return Object.keys(this.selected()).length;
  }

  requestDeleteSelected(): void {
    const ids = Object.keys(this.selected());
    if (ids.length === 0) {
      this.flash('Select rows first.');
      return;
    }
    this.pendingDeleteAction = 'selected';
    this.confirmMessage.set(`Are you sure? Delete ${ids.length} selected unused sticker(s).`);
    this.confirmOpen.set(true);
  }

  deleteSelected(): void {
    const ids = Object.keys(this.selected());
    if (ids.length === 0) return;
    this.deleting.set(true);
    this.qrService.deleteInventorySelected(ids).subscribe({
      next: (res) => {
        this.deleting.set(false);
        this.clearSelected();
        this.flash(res?.message || 'Selected stickers deleted.');
        this.reload();
      },
      error: (err) => {
        this.deleting.set(false);
        this.flash(this.apiErrorText(err, 'Delete selected failed.'));
      },
    });
  }

  requestDeleteAllFiltered(): void {
    this.pendingDeleteAction = 'all';
    this.confirmMessage.set('Are you sure? Delete all UNUSED stickers in current filter range.');
    this.confirmOpen.set(true);
  }

  deleteAllFiltered(): void {
    this.deleting.set(true);
    this.qrService.deleteInventoryAll({
      from: this.fromDate || undefined,
      to: this.toDate || undefined,
    }).subscribe({
      next: (res) => {
        this.deleting.set(false);
        this.clearSelected();
        this.flash(res?.message || 'Filtered stickers deleted.');
        this.page = 1;
        this.reload();
      },
      error: (err) => {
        this.deleting.set(false);
        this.flash(this.apiErrorText(err, 'Delete all failed.'));
      },
    });
  }

  closeConfirm(): void {
    if (this.deleting()) return;
    this.confirmOpen.set(false);
    this.confirmMessage.set('');
    this.pendingDeleteAction = null;
  }

  confirmDelete(): void {
    if (this.pendingDeleteAction === 'selected') {
      this.closeConfirm();
      this.deleteSelected();
      return;
    }
    if (this.pendingDeleteAction === 'all') {
      this.closeConfirm();
      this.deleteAllFiltered();
    }
  }

  isUnused(row: QrInventoryItem): boolean {
    return row.status === 'Unused';
  }

  printSelected(): void {
    const selectedIds = new Set(Object.keys(this.selected()));
    const ids = this.items()
      .filter((r) => selectedIds.has(r.publicId) && this.isUnused(r))
      .map((r) => r.publicId);
    if (ids.length === 0) {
      this.flash('Select at least one unused sticker.');
      return;
    }
    this.batchPrinting.set(true);
    this.qrService.printBatch(ids, this.batchEmbed, this.selectedLayout).subscribe({
      next: (html) => {
        this.batchPrinting.set(false);
        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const win = window.open(url, '_blank', 'noopener');
        if (!win) {
          URL.revokeObjectURL(url);
          this.flash('Pop-up blocked — allow pop-ups for this site, then try again.');
          return;
        }
        setTimeout(() => URL.revokeObjectURL(url), 120_000);
        this.flash(`Opened print view for ${ids.length} selected label(s).`);
      },
      error: (err) => {
        this.batchPrinting.set(false);
        this.flash(this.apiErrorText(err, 'Print selected failed.'));
      },
    });
  }

  /** QR PNG: unused → activate URL, active → /q (server embed=auto). */
  qrPngHref(row: QrInventoryItem): string {
    const id = encodeURIComponent(row.publicId.trim());
    return `/api/qr/${id}/image?embed=auto&modulePixels=32`;
  }

  /**
   * Opens one print dialog with up to 500 labels.
   * Packaging = /activate QR (unused). Sticker = /q layout + URL (unused + active).
   */
  printBatch(): void {
    const n = Math.min(this.pageSize, Math.max(1, Number(this.batchCount) || 1));
    this.batchCount = n;
    this.batchPrinting.set(true);

    const runPrint = (rows: QrInventoryItem[]) => {
      const filtered =
        rows.filter((r) => r.status === 'Unused');

      const ids = filtered.slice(0, n).map((r) => r.publicId);
      if (ids.length === 0) {
        this.batchPrinting.set(false);
        this.flash(
          'No unused tags in the list. Generate inventory first.'
        );
        return;
      }

      this.qrService.printBatch(ids, this.batchEmbed, this.batchLayout).subscribe({
      next: (html) => {
        this.batchPrinting.set(false);
        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const win = window.open(url, '_blank', 'noopener');
        if (!win) {
          URL.revokeObjectURL(url);
          this.flash('Pop-up blocked — allow pop-ups for this site, then try again.');
          return;
        }
        setTimeout(() => URL.revokeObjectURL(url), 120_000);
        if (ids.length < n) {
          this.flash(`Opened print view for ${ids.length} label(s) (only that many matched).`);
        }
      },
      error: (err) => {
        this.batchPrinting.set(false);
        this.flash(this.apiErrorText(err, 'Batch print failed.'));
      },
      });
    };

    const rowsNow = this.items();
    const filteredNow =
      rowsNow.filter((r) => r.status === 'Unused');

    runPrint(rowsNow);
  }

  /** Surfaces server message when present (e.g. DB / validation errors). */
  private apiErrorText(err: unknown, fallback: string): string {
    const e = err as { error?: unknown; message?: string; status?: number };
    const body = e?.error;
    if (typeof body === 'object' && body && 'message' in body && typeof (body as { message: string }).message === 'string') {
      return (body as { message: string }).message;
    }
    if (typeof body === 'string' && body.trim().length > 0 && body.length < 400) {
      return body.trim();
    }
    if (e?.status === 0) {
      return 'Network error — check that the API is running and you use the same host/port as the app.';
    }
    return fallback;
  }

  formatDateTime(value: string | null): string {
    if (!value) return '—';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleString();
  }

  private setDefaultDateRange(): void {
    const today = new Date();
    const monthBack = new Date(today);
    monthBack.setMonth(monthBack.getMonth() - 1);
    this.fromDate = this.formatDateInput(monthBack);
    this.toDate = this.formatDateInput(today);
  }

  private formatDateInput(dt: Date): string {
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  openActivateFree(row: QrInventoryItem): void {
    this.selectedSticker.set(row);
    this.freeFormData = {
      name: '',
      phoneNumber: '',
      phoneNumberType: 'Mobile',
      email: '',
      address: '',
      fatherName: '',
      vehicleRegistration: '',
      emergencyContactPhone: '',
      emergencyContactPhoneType: 'Mobile',
    };
    this.activeFreeOpen.set(true);
  }

  closeActivateFree(): void {
    if (this.submittingFree()) return;
    this.activeFreeOpen.set(false);
    this.selectedSticker.set(null);
  }

  submitActivateFree(ev: Event): void {
    ev.preventDefault();
    const row = this.selectedSticker();
    if (!row) return;

    this.submittingFree.set(true);
    this.qrService.activateFree(row.publicId, this.freeFormData).subscribe({
      next: (res) => {
        this.submittingFree.set(false);
        this.activeFreeOpen.set(false);
        this.flash(res?.message || 'Free activation successful.');
        this.reload();
      },
      error: (err) => {
        this.submittingFree.set(false);
        this.flash(this.apiErrorText(err, 'Free activation failed.'));
      },
    });
  }
}
