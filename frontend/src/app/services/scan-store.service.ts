// src/app/services/scan-store.service.ts
import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { ScanResult } from './scan-broadcast.service';

@Injectable({
    providedIn: 'root'
})
export class ScanStoreService {
    /**
     * Holds the most recent scan result (for tabs opened after scan).
     * This is a fallback — primary communication uses BroadcastChannel.
     */
    private currentScan = new BehaviorSubject<ScanResult | null>(null);

    /**
     * Observable stream of the current scan result.
     */
    currentScan$ = this.currentScan.asObservable();

    /**
     * Store a scan result for late subscribers.
     */
    setScan(scan: ScanResult): void {
        this.currentScan.next(scan);
    }

    /**
     * Clear the stored scan (e.g., after avatar consumes it).
     */
    clearScan(): void {
        this.currentScan.next(null);
    }

    /**
     * Get the current stored scan (for immediate use on component init).
     */
    getCurrentScan(): ScanResult | null {
        return this.currentScan.value;
    }
}