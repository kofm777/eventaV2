import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
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
  
  // Camera selection
  cameras: MediaDeviceInfo[] = [];
  selectedCamera: string = '';

  constructor(private apiService: ApiService) {
    this.codeReader = new BrowserMultiFormatReader();
  }

  async ngOnInit() {
    await this.loadCameras();
  }

  ngOnDestroy() {
    this.stopScanning();
  }

  async loadCameras() {
    try {
      const devices = await this.codeReader.listVideoInputDevices();
      this.cameras = devices;
      if (devices.length > 0) {
        this.selectedCamera = devices[0].deviceId;
      }
    } catch (err) {
      console.error('Error loading cameras:', err);
      this.error = 'Impossible d\'accéder à la caméra. Vérifiez les permissions.';
    }
  }

  async startScanning() {
    if (!this.selectedCamera) {
      this.error = 'Veuillez sélectionner une caméra.';
      return;
    }

    this.scanning = true;
    this.error = null;
    this.success = null;

    try {
      await this.codeReader.decodeFromVideoDevice(
        this.selectedCamera,
        this.videoElement.nativeElement,
        (result, err) => {
          if (result) {
            const qrCode = result.getText();
            this.processScan(qrCode);
          }
          if (err && !(err instanceof NotFoundException)) {
            console.error('Scan error:', err);
          }
        }
      );
    } catch (err) {
      console.error('Error starting scanner:', err);
      this.error = 'Erreur lors du démarrage du scanner.';
      this.scanning = false;
    }
  }

  stopScanning() {
    this.codeReader.reset();
    this.scanning = false;
  }

  processScan(qrCode: string) {
    // Temporarily stop scanning to prevent multiple scans
    this.stopScanning();

    const scanData = {
      payload: qrCode,
      scanner_user: this.scannerUser || undefined
    };

    this.apiService.scan(scanData).subscribe({
      next: (response) => {
        if (response.ok) {
          this.success = response.message;
          this.lastScanResult = response.participant;
          
          // Play success sound (optional)
          this.playSuccessSound();
          
          // Auto-restart scanning after 3 seconds
          setTimeout(() => {
            this.success = null;
            this.lastScanResult = null;
            if (this.selectedCamera) {
              this.startScanning();
            }
          }, 3000);
        } else {
          this.error = response.message;
          this.playErrorSound();
          
          // Auto-restart scanning after 2 seconds
          setTimeout(() => {
            this.error = null;
            if (this.selectedCamera) {
              this.startScanning();
            }
          }, 2000);
        }
      },
      error: (err) => {
        this.error = err.error?.message || 'Erreur lors de la vérification du QR code.';
        this.playErrorSound();
        
        // Auto-restart scanning after 2 seconds
        setTimeout(() => {
          this.error = null;
          if (this.selectedCamera) {
            this.startScanning();
          }
        }, 2000);
      }
    });
  }

  scanManualCode() {
    if (!this.manualQrCode.trim()) {
      this.error = 'Veuillez entrer un code QR.';
      return;
    }

    this.processScan(this.manualQrCode);
    this.manualQrCode = '';
  }

  playSuccessSound() {
    // Create a simple beep sound for success
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
    // Create a simple beep sound for error
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
      case 'foire':
        return 'Foire';
      case 'conference':
        return 'Conférence';
      case 'both':
        return 'Foire + Conférence';
      default:
        return accessType;
    }
  }
}
