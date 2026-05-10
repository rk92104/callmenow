import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Person, PersonDto, QRCodeResponse } from '../models/person.model';

@Injectable({
    providedIn: 'root'
})
export class PersonService {
    private readonly apiUrl = '/api/persons';

    constructor(private readonly http: HttpClient) { }

    getAll(): Observable<Person[]> {
        return this.http.get<Person[]>(this.apiUrl);
    }

    getById(id: number): Observable<Person> {
        return this.http.get<Person>(`${this.apiUrl}/${id}`);
    }

    create(person: PersonDto): Observable<Person> {
        return this.http.post<Person>(this.apiUrl, person);
    }

    update(id: number, person: PersonDto): Observable<Person> {
        return this.http.put<Person>(`${this.apiUrl}/${id}`, person);
    }

    delete(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/${id}`);
    }

    getQRCodeBase64(id: number): Observable<QRCodeResponse> {
        return this.http.get<QRCodeResponse>(`${this.apiUrl}/${id}/qrcode-base64`);
    }
}
