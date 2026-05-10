import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  ActivateQrPayload,
  QrInventoryItem,
  QrInventoryListResponse,
  QrScanResponse,
  RazorpayOrderResponse,
} from '../models/qr.model';
import { QRCodeResponse } from '../models/person.model';

@Injectable({
  providedIn: 'root',
})
export class QrService {
  private readonly base = '/api';

  /** Bump when print HTML/CSS changes so browser does not reuse cached label document. */
  /** Bump when server print HTML/CSS changes (cache-bust for /api/.../print/label URLs). */
  private static readonly printLabelVersion = 'sticker-v12-vert-55x748-5x5';

  constructor(private readonly http: HttpClient) {}

  scan(publicId: string): Observable<QrScanResponse> {
    const id = encodeURIComponent(publicId.trim());
    return this.http.get<QrScanResponse>(`${this.base}/qr/${id}/scan`);
  }

  /**
   * Exotel Connect (masked): order is server-controlled (`EXOTEL_RING_OWNER_FIRST` in PHP config).
   * Default rings scanner first; with owner-first, owner is dialed first then scanner joins.
   */
  exotelConnectOwner(publicId: string, fromPhone?: string, emergency = false): Observable<{ message: string }> {
    const id = encodeURIComponent(publicId.trim());
    const body: { fromPhone?: string } = {};
    const p = fromPhone?.trim();
    if (p) {
      body.fromPhone = p;
    }
    return this.http.post<{ message: string }>(`${this.base}/qr/${id}/exotel/connect-owner?emergency=${emergency}`, body);
  }

  getExotelBrowserToken(publicId: string): Observable<{ token: string }> {
    const id = encodeURIComponent(publicId.trim());
    return this.http.get<{ token: string }>(`${this.base}/qr/${id}/exotel/browser-token`);
  }

  activate(publicId: string, body: ActivateQrPayload): Observable<{ message: string; scanUrl?: string }> {
    const id = encodeURIComponent(publicId.trim());
    return this.http.post<{ message: string; scanUrl?: string }>(
      `${this.base}/qr/${id}/activate`,
      body
    );
  }

  activateFree(publicId: string, data: any): Observable<{ message: string }> {
    const id = encodeURIComponent(publicId.trim());
    return this.http.post<{ message: string }>(`${this.base}/qr/${id}/activate-free`, data);
  }

  createRazorpayOrder(publicId: string, referralCode?: string): Observable<RazorpayOrderResponse> {
    return this.http.post<RazorpayOrderResponse>(`${this.base}/payments/razorpay/order`, {
      publicId: publicId.trim(),
      referralCode: referralCode?.trim() || undefined,
    });
  }

  listInventory(params?: {
    page?: number;
    pageSize?: number;
    from?: string;
    to?: string;
    search?: string;
    status?: string;
  }): Observable<QrInventoryListResponse> {
    const q = new URLSearchParams();
    if (params?.page) q.set('page', String(params.page));
    if (params?.pageSize) q.set('pageSize', String(params.pageSize));
    if (params?.from) q.set('from', params.from);
    if (params?.to) q.set('to', params.to);
    if (params?.search) q.set('search', params.search);
    if (params?.status) q.set('status', params.status);
    const qs = q.toString();
    const url = `${this.base}/inventory/qr${qs ? `?${qs}` : ''}`;
    return this.http.get<QrInventoryListResponse>(url);
  }

  generateInventory(count: number, productType: string): Observable<QrInventoryItem[]> {
    return this.http.post<QrInventoryItem[]>(`${this.base}/inventory/qr/generate`, {
      count,
      productType,
    });
  }

  /** A4-style HTML label (opens print dialog). embed: activate = packaging QR, scan = public sticker QR. */
  printLabelHref(publicId: string, embed: 'activate' | 'scan' = 'activate'): string {
    const id = encodeURIComponent(publicId.trim());
    const v = encodeURIComponent(QrService.printLabelVersion);
    return `${this.base}/inventory/qr/print/label/${id}?embed=${embed}&v=${v}`;
  }

  printBatch(
    publicIds: string[],
    embed: 'activate' | 'scan' = 'activate',
    layout: 'horizontal' | 'vertical' = 'horizontal'
  ): Observable<string> {
    return this.http.post(
      `${this.base}/inventory/qr/print/batch`,
      { publicIds, embed, layout },
      { responseType: 'text' }
    );
  }

  deleteInventorySelected(publicIds: string[]): Observable<{ deletedCount: number; message: string }> {
    return this.http.post<{ deletedCount: number; message: string }>(
      `${this.base}/inventory/qr/delete`,
      { publicIds }
    );
  }

  deleteInventoryAll(params?: { from?: string; to?: string }): Observable<{ deletedCount: number; message: string }> {
    const q = new URLSearchParams();
    if (params?.from) q.set('from', params.from);
    if (params?.to) q.set('to', params.to);
    const qs = q.toString();
    const url = `${this.base}/inventory/qr/delete-all${qs ? `?${qs}` : ''}`;
    return this.http.post<{ deletedCount: number; message: string }>(url, {});
  }

  getOwnerQrBase64(personId: number): Observable<QRCodeResponse> {
    return this.http.get<QRCodeResponse>(`${this.base}/qr/by-owner/${personId}/base64`);
  }

  submitMarketingLead(phone: string, publicId: string, referralCode: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.base}/qr/marketing/leads`, {
      phone,
      publicId: publicId || undefined,
      referralCode: referralCode || undefined,
    });
  }
}
