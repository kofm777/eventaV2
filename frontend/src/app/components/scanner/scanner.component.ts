import { Component, OnInit, OnDestroy, ViewChild, ElementRef,NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BrowserMultiFormatReader, NotFoundException } from '@zxing/library';
import { ApiService } from '../../services/api.service';
import { SpeakerAvatarComponent } from '../speaker-avatar/speaker-avatar.component';

@Component({
  selector: 'app-scanner',
  standalone: true,
  imports: [CommonModule, FormsModule, SpeakerAvatarComponent],
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

  constructor(private apiService: ApiService, private ngZone: NgZone) {
    this.codeReader = new BrowserMultiFormatReader();
  }

  async ngOnInit() {
    await this.loadCameras();

    // Auto-start on mobile (if only one camera)
    if (this.cameras.length === 1) {
      this.selectedCamera = this.cameras[0].deviceId;
      this.startScanning();
    }
  }

  ngOnDestroy() {
    this.stopScanning();
  }

  async loadCameras() {
    try {
      const devices = await this.codeReader.listVideoInputDevices();
      this.cameras = devices;
      if (devices.length > 0 && !this.selectedCamera) {
        // Auto-select first camera (common on mobile)
        this.selectedCamera = devices[0].deviceId;
      }
    } catch (err) {
      console.error('Camera access error:', err);
      this.error = 'Unable to access camera. Check permissions.';
    }
  }

  // ✅ Auto-start scanning when camera is selected
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
              // Run in NgZone to ensure UI updates
              this.ngZone.run(() => {
                const qrCode = result.getText();
                this.processTextScan(qrCode);
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

    if (err instanceof Error) {
      if (err.name === 'NotAllowedError' || err.message.includes('NotAllowedError')) {
        errorMessage = 'Camera permission denied. Please allow access and refresh.';
      }
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

    this.apiService.scan(scanData).subscribe({
      next: (response) => {
        this.success = response.ok ? response.message : null;
        this.error = !response.ok ? response.message : null;
        this.lastScanResult = response.ok ? response.participant : null;

        if (response.ok) {
          this.playSuccessSound();
        } else {
          this.playErrorSound();
        }

        // Auto-restart scanning after 3 seconds
        setTimeout(() => {
          if (this.selectedCamera) {
            this.startScanning();
          }
        }, 3000);
      },
      error: (err) => {
        this.error = err.error?.message || 'Verification failed.';
        this.lastScanResult = null;
        this.playErrorSound();
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  scanManualCode() {
    if (!this.manualQrCode.trim()) {
      this.error = "Please enter a QR code.";
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

  private processImageScan(base64Image: string) {
    const scanData = {
      qr_image: base64Image,
      scanner_user: this.scannerUser || undefined
    };

    this.apiService.scan(scanData).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = response.message;
          this.lastScanResult = response.participant;
          this.playSuccessSound();
        } else {
          this.error = response.message;
          this.lastScanResult = null;
          this.playErrorSound();
          setTimeout(() => this.error = null, 3000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || "Error verifying QR code.";
        this.lastScanResult = null;
        this.playErrorSound();
        setTimeout(() => this.error = null, 3000);
      }
    });
  }

  playSuccessSound() {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.2);
  }

  playErrorSound() {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.frequency.value = 400;
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
  }

  getAccessTypeLabel(accessType: string): string {
    switch (accessType) {
      case 'fair':
        return 'fair';
      case 'conference':
        return 'Conférence';
      case 'fair + conference':
        return 'Foire + Conférence';
      default:
        return accessType;
    }
  }
}