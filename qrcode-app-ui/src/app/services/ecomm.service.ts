import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface StickerOrder {
  customerName: string;
  customerPhone: string;
  shippingAddress: string;
  city: string;
  pincode: string;
  productId: string;
  productName: string;
  amount: number;
}

@Injectable({
  providedIn: 'root'
})
export class EcommService {
  private readonly base = '/api/ecomm';

  constructor(private readonly http: HttpClient) {}

  bookSticker(order: StickerOrder): Observable<{ message: string; orderId: number }> {
    return this.http.post<{ message: string; orderId: number }>(`${this.base}/book`, order);
  }

  trackOrder(phone: string): Observable<any> {
    return this.http.get<any>(`${this.base}/track?phone=${phone}`);
  }
}
