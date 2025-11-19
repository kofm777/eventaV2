// src/app/pages/scanner/scanner.component.ts
import { Component, OnInit, OnDestroy, ViewChild, ElementRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BrowserMultiFormatReader, NotFoundException } from '@zxing/library';
import { ApiService } from '../../services/api.service';
import { ScanBroadcastService } from '../../services/scan-broadcast.service';

@Component({
  selector: 'app-scanner',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './scanner.component.html',
  styleUrls: ['./scanner.component.css']
})
export class ScannerComponent implements OnInit, OnDestroy {
  @ViewChild('video', { static: false }) videoElement!: ElementRef<HTMLVideoElement>;

  codeReader: BrowserMultiFormatReader;
  scanning = false;
  error: string | null = null;
  success: string | null = null;
  lastScanResult: any = null;
  scannerUser = '';
  manualQrCode = '';

  cameras: MediaDeviceInfo[] = [];
  selectedCamera: string = '';

  constructor(
      private apiService: ApiService,
      private ngZone: NgZone,
      private scanBroadcast: ScanBroadcastService
  ) {
    this.codeReader = new BrowserMultiFormatReader();
  }

  ngOnInit() {
    this.loadCameras().then(() => {
      if (this.cameras.length === 1) {
        this.selectedCamera = this.cameras[0].deviceId;
        this.startScanning();
      }
    });
  }

  ngOnDestroy() {
    this.stopScanning();
  }

  async loadCameras() {
    try {
      const devices = await this.codeReader.listVideoInputDevices();
      this.cameras = devices;
      if (devices.length > 0 && !this.selectedCamera) {
        this.selectedCamera = devices[0].deviceId;
      }
    } catch (err) {
      console.error('Camera access error:', err);
      this.error = 'Unable to access camera. Check permissions.';
    }
  }

  onCameraSelected(deviceId: string) {
    if (deviceId) {
      this.selectedCamera = deviceId;
      this.startScanning();
    } else {
      this.stopScanning();
    }
  }

  async startScanning() {
    if (!this.selectedCamera) return;

    this.scanning = true;
    this.error = null;
    this.success = null;

    try {
      await this.codeReader.decodeFromVideoDevice(
          this.selectedCamera,
          this.videoElement.nativeElement,
          (result, err) => {
            if (result) {
              this.ngZone.run(() => {
                this.processTextScan(result.getText());
              });
            }
            if (err && !(err instanceof NotFoundException)) {
              console.warn('Scanning error:', err);
            }
          }
      );
    } catch (err: unknown) {
      console.error('Failed to start scanner:', err);
      let errorMessage = 'Error starting camera. Try another device.';
      if (err instanceof Error && (err.name === 'NotAllowedError' || err.message.includes('NotAllowedError'))) {
        errorMessage = 'Camera permission denied. Please allow access and refresh.';
      }
      this.ngZone.run(() => {
        this.error = errorMessage;
        this.scanning = false;
      });
    }
  }

  stopScanning() {
    this.codeReader.reset();
    this.scanning = false;
  }

  private processTextScan(qrCode: string) {
    this.stopScanning();
    const scanData = {
      payload: qrCode,
      scanner_user: this.scannerUser || undefined
    };
    this.apiService.scanFair(scanData).subscribe({
      next: (response) => this.handleScanResult(response),
      error: (err) => this.handleScanResult({ ok: false, message: err.error?.message || 'Verification failed.', participant: null })
    });
  }

  private processImageScan(base64Image: string) {
    const scanData = {
      qr_image: base64Image,
      scanner_user: this.scannerUser || undefined
    };
    this.apiService.scanFair(scanData).subscribe({
      next: (response) => this.handleScanResult(response),
      error: (err) => this.handleScanResult({ ok: false, message: err.error?.message || 'Error verifying QR code.', participant: null })
    });
  }

  private handleScanResult(response: any) {
    this.lastScanResult = response.participant || null;
    this.success = response.ok ? response.message : null;
    this.error = !response.ok ? response.message : null;

    this.scanBroadcast.broadcastFair({
      participant: response.participant,
      is_already_scanned: response.is_already_scanned,
      message: response.message,
      scanType: 'fair'
    });

    if (response.ok) {
      this.playSuccessSound();
    } else {
      this.playErrorSound();
      setTimeout(() => this.error = null, 3000);
    }
  }

  scanManualCode() {
    if (!this.manualQrCode.trim()) {
      this.error = 'Please enter a QR code.';
      return;
    }
    this.processTextScan(this.manualQrCode);
    this.manualQrCode = '';
  }

  onFileSelected(event: any) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      const base64String = (e.target?.result as string).split(',')[1];
      this.processImageScan(base64String);
    };
    reader.readAsDataURL(file);
    event.target.value = '';
  }

  playSuccessSound() {
    this.playBeep(800, 0.2);
  }

  playErrorSound() {
    this.playBeep(400, 0.3);
  }

  private playBeep(frequency: number, duration: number) {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.frequency.value = frequency;
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + duration);
  }

  getAccessTypeLabel(accessType: string): string {
    switch (accessType) {
      case 'fair': return 'Fair only';
      case 'fair + conference': return 'Fair and Conference';
      default: return accessType;
    }
  }
}