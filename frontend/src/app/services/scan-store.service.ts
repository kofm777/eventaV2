// src/app/services/scan-store.service.ts
import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { ScanResult } from './scan-broadcast.service';

@Injectable({
    providedIn: 'root'
})
export class ScanStoreService {
    private currentScan = new BehaviorSubject<ScanResult | null>(null);
    currentScan$ = this.currentScan.asObservable();

    setScan(scan: ScanResult) {
        this.currentScan.next(scan);
    }

    clearScan() {
        this.currentScan.next(null);
    }
}