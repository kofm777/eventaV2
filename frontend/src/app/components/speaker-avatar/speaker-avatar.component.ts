import { Component, Input, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-speaker-avatar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './speaker-avatar.component.html',
  styleUrls: ['./speaker-avatar.component.css']
})
export class SpeakerAvatarComponent implements OnInit, OnDestroy {
  @Input() participantName: string = '';
  @Input() gender: string = 'Femme';
  @Input() autoPlay: boolean = true;
  @Input() accessStatus: string = 'Accepted'; // 'Accepted' or 'Denied'
  @Input() accessType: string = 'both'; // 'foire', 'conference', 'both'

  private synth: SpeechSynthesis;
  private utterance: SpeechSynthesisUtterance | null = null;
  private voicesLoaded = false;
  private retryTimeout: any;

  isPlaying = false;
  isSpeaking = false;
  videoReady = false;
  error: string | null = null;

  // TTS Configuration
  private frenchVoices: SpeechSynthesisVoice[] = [];
  private selectedVoice: SpeechSynthesisVoice | null = null;

  constructor() {
    this.synth = window.speechSynthesis;
  }

  ngOnInit() {
    this.initVoices();

    if (this.autoPlay && this.participantName) {
      // Delay auto-play slightly to ensure voices are ready
      setTimeout(() => {
        // Avoid browser auto-play block by checking if window has focus
        if (document.hasFocus()) {
          this.playWelcomeMessage();
        } else {
          console.warn('TTS autoplay deferred until user interaction.');
          const handler = () => {
            this.playWelcomeMessage();
            document.removeEventListener('click', handler);
          };
          document.addEventListener('click', handler);
        }
      }, 800);
    }
  }

  ngOnDestroy() {
    this.stopSpeaking();
    if (this.retryTimeout) clearTimeout(this.retryTimeout);
  }

  /** Initialize and load French voices */
  private initVoices() {
    this.loadVoices();

    // Re-load when voices become available (Chrome quirk)
    if (typeof speechSynthesis !== 'undefined') {
      speechSynthesis.onvoiceschanged = () => {
        this.loadVoices();
      };
    }
  }

  /** Load and select a suitable voice */
  private loadVoices() {
    const voices = this.synth.getVoices();

    if (!voices.length) {
      // Retry loading until available
      setTimeout(() => this.loadVoices(), 300);
      return;
    }

    this.frenchVoices = voices.filter(v =>
      v.lang.toLowerCase().startsWith('fr')
    );

    this.selectedVoice =
      this.frenchVoices.find(v =>
        ['amelie', 'female', 'femme', 'thomas'].some(k =>
          v.name.toLowerCase().includes(k)
        )
      ) || this.frenchVoices[0] || voices[0];

    this.voicesLoaded = true;

    console.log('Loaded voices:', this.frenchVoices);
    console.log('Selected voice:', this.selectedVoice?.name);
  }

  /** Plays the welcome message */
  async playWelcomeMessage() {
    if (!this.participantName) {
      this.error = 'Aucun nom de participant fourni.';
      return;
    }

    // Wait until voices are loaded
    if (!this.voicesLoaded) {
      await new Promise(resolve => setTimeout(resolve, 800));
    }

    const message = this.generateWelcomeMessage();
    await this.speak(message);
  }

  /** Builds the personalized message */
  private generateWelcomeMessage(): string {
    const fullName = this.participantName.trim();
    const honorific = this.getHonorific();

    const statusMessage =
      this.accessStatus === 'Accepted'
        ? 'Accès autorisé. Bienvenue à notre événement.'
        : 'Accès refusé. Veuillez contacter l\'administration.';

    const accessTypeMessage = this.getAccessTypeMessage();
    const qrMessage =
      this.accessStatus === 'Accepted'
        ? 'Votre code QR a été scanné avec succès.'
        : '';

    return `Cher participant ${honorific} ${fullName}. ${statusMessage} ${accessTypeMessage} ${qrMessage}`.trim();
  }

  private getHonorific(): string {
    if (this.gender === 'Homme') return 'Monsieur';
    if (this.gender === 'Femme') return 'Madame';
    return '';
  }

  private getAccessTypeMessage(): string {
    switch (this.accessType) {
      case 'foire':
        return 'Vous avez accès à la foire uniquement.';
      case 'conference':
        return 'Vous avez accès à la conférence uniquement.';
      case 'both':
        return 'Vous avez accès à la foire et à la conférence.';
      default:
        return '';
    }
  }

  /** Robust speech synthesis function */
  private async speak(text: string) {
    if (!('speechSynthesis' in window)) {
      this.error = 'La synthèse vocale n\'est pas supportée par votre navigateur.';
      return;
    }

    // Prevent overlap
    if (this.synth.speaking) {
      this.synth.cancel();
      await new Promise(r => setTimeout(r, 150));
    }

    this.utterance = new SpeechSynthesisUtterance(text);
    this.utterance.lang = 'fr-FR';
    this.utterance.rate = 0.85;
    this.utterance.pitch = 1.0;
    this.utterance.volume = 1.0;
    if (this.selectedVoice) this.utterance.voice = this.selectedVoice;

    // Event handlers
    this.utterance.onstart = () => {
      this.isSpeaking = true;
      this.isPlaying = true;
      this.error = null;
    };

    this.utterance.onend = () => {
      this.isSpeaking = false;
      this.isPlaying = false;
    };

    this.utterance.onerror = (event) => {
      console.error('Speech synthesis error:', event);
      this.isSpeaking = false;
      this.isPlaying = false;

      if (event.error === 'interrupted') {
        console.warn('Speech interrupted, retrying...');
        this.retryTimeout = setTimeout(() => this.speak(text), 800);
      } else {
        this.error = 'Erreur lors de la lecture du message.';
      }
    };

    this.synth.speak(this.utterance);
  }

  stopSpeaking() {
    if (this.synth.speaking) {
      this.synth.cancel();
    }
    this.isSpeaking = false;
    this.isPlaying = false;
  }

  replay() {
    this.playWelcomeMessage();
  }

  getAccessTypeDisplay(): string {
    switch (this.accessType) {
      case 'foire':
        return 'Foire uniquement';
      case 'conference':
        return 'Conférence uniquement';
      case 'both':
        return 'Foire et Conférence';
      default:
        return '';
    }
  }

  // Video placeholder methods (for avatar video integration)
  onVideoReady() {
    this.videoReady = true;
  }

  onVideoError() {
    this.error = 'Erreur lors du chargement de la vidéo.';
  }
}
