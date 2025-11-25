window.POSVoiceChat = {
	pc: null,
	dc: null,
	audioEl: null,
	ms: null,
	isActive: false,
	messagesContainer: null,
	messageInput: null,
	startSessionButton: null,
	audioInputSelect: null,
	audioOutputSelect: null,
	backscroll: [],

	init() {
		document.addEventListener('DOMContentLoaded', this.onLoad.bind(this));
	},

	onLoad() {
		this.messagesContainer = document.getElementById('messages');
		this.messageInput = document.getElementById('message-input');
		this.startSessionButton = document.getElementById('start-session');
		this.audioInputSelect = document.getElementById('audio-input');
		this.audioOutputSelect = document.getElementById('audio-output');

		// Set up device selectors
		this.setupDeviceSelectors();

		document.getElementById('send-button').addEventListener('click', () => {
			this.sendMessage();
		});
		this.messageInput.onkeydown = (e) => {
			if ( e.key === 'Enter' ) {
				this.sendMessage();
			}
		};

		this.startSessionButton.addEventListener('click', () => {
			this.realtimeChatInit(this.startSessionButton);
		});
		// Get query param 'q' and populate message input if present
		const urlParams = new URLSearchParams(window.location.search);
		const queryMessage = urlParams.get('q');
		if (queryMessage) {
			this.messageInput.value = queryMessage;
			this.sendMessage();
		}
	},

	sendMessage() {
		if ( this.messageInput.value.length === 0 ) {
			return;
		}
		const message = this.messageInput.value;
		this.addMessage(message, 'user');
		this.messageInput.value = '';
		if ( ! this.dc || ! this.isActive ) {
			// Use without voice mode.
			wp.apiFetch({
				path: '/pos/v1/openai/chat/assistant',
				method: 'POST',
				data: {
					messages: this.backscroll,
				}
			}).then(response => {
				this.resetBackscroll(response);
			});
			return;
		}

		this.dc.send(JSON.stringify({
			type: 'conversation.item.create',
			item: {
				type: 'message',
				role: 'user',
				content: [ {
					type: 'input_text',
					text: message,
				} ]
			}
		}));
		this.dc.send(JSON.stringify({
			type: 'response.create',
			response: {
				modalities: ['text'],
			}
		}));
	},
	async setupDeviceSelectors() {
		try {
			const devices = await navigator.mediaDevices.enumerateDevices();
			
			// Clear existing options
			this.audioInputSelect.innerHTML = '';
			this.audioOutputSelect.innerHTML = '';

			// Populate input devices
			devices.filter(device => device.kind === 'audioinput')
				.forEach(device => {
					const option = document.createElement('option');
					option.value = device.deviceId;
					option.text = device.label || `Microphone ${this.audioInputSelect.length + 1}`;
					this.audioInputSelect.appendChild(option);
					if(device.label === 'AirPods') {
						option.selected = true;
					}
				});

			// Populate output devices
			devices.filter(device => device.kind === 'audiooutput')
				.forEach(device => {
					const option = document.createElement('option');
					option.value = device.deviceId;
					option.text = device.label || `Speaker ${this.audioOutputSelect.length + 1}`;
					this.audioOutputSelect.appendChild(option);
					if(device.label === 'AirPods') {
						option.selected = true;
					}
				});

			// Add change listeners
			this.audioInputSelect.addEventListener('change', () => {
				if (this.isActive) {
					this.restartAudioInput();
				}
			});

			this.audioOutputSelect.addEventListener('change', () => {
				if (this.audioEl && this.audioEl.setSinkId) {
					this.audioEl.setSinkId(this.audioOutputSelect.value);
				}
			});
		} catch (error) {
			console.error('Error setting up device selectors:', error);
		}
	},

	async restartAudioInput() {
		if (this.ms) {
			this.ms.getTracks().forEach(track => track.stop());
		}

		this.ms = await navigator.mediaDevices.getUserMedia({
			audio: {
				deviceId: this.audioInputSelect.value ? { exact: this.audioInputSelect.value } : undefined
			}
		});

		const sender = this.pc.getSenders().find(s => s.track.kind === 'audio');
		if (sender) {
			sender.replaceTrack(this.ms.getTracks()[0]);
		}
	},

	markdownToHtml(markdown) {
		return markdown
			.replace(/\n/g, '<br>')
			.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
			.replace(/__(.*?)__/g, '<u>$1</u>')
			.replace(/\[(.*?)\]\((.*?)\)/g, '<a target="_blank" href="$2">$1</a>')
			.replace(/^\s*[-*]\s(.*)$/gm, '<li>$1</li>')
			.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
	},

	addMessage(text, sender) {
		this.backscroll.push({
			content: text,
			role: sender,
		});
		this.renderMessage(text, sender);
	},

	renderMessage( text, sender ) {
		const messageBubble = document.createElement('div');
		messageBubble.classList.add('message', sender);
		if ( sender === 'tool' ) {
			messageBubble.innerHTML = `<div class='tool-header'>Calling tool ⬇️</div><pre>${text}</pre>`;
		} else {
			messageBubble.innerHTML = this.markdownToHtml(text);
		}

		this.messagesContainer.appendChild(messageBubble);
		this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
	},

	resetBackscroll( newBackscroll ) {
		this.backscroll = newBackscroll;
		this.messagesContainer.innerHTML = '';
		this.backscroll.forEach(message => {
			if ( message.content ) {
				this.renderMessage(message.content, message.role);
			}
		});
	},

	async realtimeChatInit(button) {

		// If session is active, hang up
		if (this.isActive) {
			this.hangUpSession();
			button.textContent = "Start Session";
			document.getElementById('chat-container').classList.remove("session_active");
			return;
		}
		document.getElementById('chat-container').classList.add("session_active");
		button.textContent = "Connecting...";

		// Get ephemeral key from server
		const tokenResponse = await wp.apiFetch({
			path: '/pos/v1/openai/realtime/session',
			method: 'POST',
		});
		
		const EPHEMERAL_KEY = tokenResponse.client_secret.value;

		// Create a peer connection
		this.pc = new RTCPeerConnection();

		// Set up audio element
		this.audioEl = document.createElement("audio");
		this.audioEl.autoplay = true;
		document.body.appendChild(this.audioEl);
		this.pc.ontrack = e => this.audioEl.srcObject = e.streams[0];

		// Update getUserMedia to use selected input device
		this.ms = await navigator.mediaDevices.getUserMedia({
			audio: {
				deviceId: this.audioInputSelect.value ? { exact: this.audioInputSelect.value } : undefined
			}
		});
		this.pc.addTrack(this.ms.getTracks()[0]);

		// Set audio output if supported
		if (this.audioEl.setSinkId && this.audioOutputSelect.value) {
			try {
				await this.audioEl.setSinkId(this.audioOutputSelect.value);
			} catch (error) {
				console.error('Error setting audio output device:', error);
			}
		}

		// Set up data channel
		this.dc = this.pc.createDataChannel("oai-events");
		this.dc.addEventListener('open', () => {
			button.textContent = "Hang Up";
			//button.classList.add("session_active");

			this.dc.send(JSON.stringify({
				type: 'response.create',
				response: {
					"instructions": "Ask how can you",
				}
			}));
		});

		this.setupDataChannelListeners();

		// Start session
		const offer = await this.pc.createOffer();
		await this.pc.setLocalDescription(offer);

		const baseUrl = "https://api.openai.com/v1/realtime";
		const model = "gpt-4o-realtime-preview-2024-12-17";
		const sdpResponse = await fetch(`${baseUrl}?model=${model}`, {
			method: "POST",
			body: offer.sdp,
			headers: {
				Authorization: `Bearer ${EPHEMERAL_KEY}`,
				"Content-Type": "application/sdp"
			},
		});

		const answer = {
			type: "answer",
			sdp: await sdpResponse.text(),
		};
		await this.pc.setRemoteDescription(answer);
		this.isActive = true;
	},

	setupDataChannelListeners() {
		this.dc.addEventListener("message", (e) => {
			const data = JSON.parse(e.data);
			if (data.type === 'response.function_call_arguments.done') {
				console.log('FUNCTION CALL ARGUMENTS DONE', data.name, data.arguments);
				this.addMessage('Calling function ' + data.name, 'assistant');
				// Convert tool name back to ability name: __ → /, _ → -
				const abilityName = data.name.replace(/__/g, '/').replace(/_/g, '-');
				const input = data.arguments ? JSON.parse(data.arguments) : {};
				const basePath = `/wp-abilities/v1/abilities/${abilityName}/run`;

				// Try POST first, fall back to GET for read-only abilities
				console.log('Ability call:', { abilityName, input, basePath });
				wp.apiFetch({
					path: basePath,
					method: 'POST',
					data: { input }
				}).catch(error => {
					console.log('POST failed, error:', error);
					if (error.code === 'rest_ability_invalid_method') {
						// Read-only ability, use GET with input params in query string
						const params = new URLSearchParams();
						for (const [key, value] of Object.entries(input)) {
							params.append(`input[${key}]`, typeof value === 'object' ? JSON.stringify(value) : value);
						}
						const queryString = params.toString() ? '?' + params.toString() : '';
						console.log('Retrying with GET:', { path: basePath + queryString, params: params.toString() });
						return wp.apiFetch({
							path: basePath + queryString,
							method: 'GET'
						});
					}
					throw error;
				}).then(response => {
					const result = JSON.stringify(response);
					this.addMessage( JSON.stringify( response, null, 2), 'tool');
					this.dc.send(JSON.stringify({
						type: 'conversation.item.create',
						item: {
							type: 'function_call_output',
							call_id: data.call_id,
							output: result
						}
					}));
					this.dc.send(JSON.stringify({
						type: 'response.create'
					}));
				});
			} else if (data.type === 'response.audio_transcript.done') {
				console.log('MODEL', data.transcript);
				this.addMessage(data.transcript, 'assistant');
			} else if (data.type === 'conversation.item.input_audio_transcription.completed') {
				console.log('USER', data.transcript);
				this.addMessage(data.transcript, 'user');
			} else if (data.type === 'output_audio_buffer.audio_started') {
				console.log('BUFFER AUDIO STARTED');
				document.getElementById('chat-container').classList.add("speaking");
			} else if (data.type === 'output_audio_buffer.audio_stopped') {
				console.log('BUFFER AUDIO STOPPED');
				document.getElementById('chat-container').classList.remove("speaking");
			} else {
				//console.log('OTHER', data );
			}
			
		});
	},

	hangUpSession() {
		if (this.pc) {
			this.pc.close();
		}
		if (this.dc) {
			this.dc.close();
		}
		if (this.ms) {
			this.ms.getTracks().forEach(track => track.stop());
		}
		if (this.audioEl) {
			this.audioEl.remove();
		}

		// Reset all variables
		this.pc = null;
		this.dc = null;
		this.audioEl = null;
		this.ms = null;
		this.isActive = false;
	}
};

// Initialize the chat
POSVoiceChat.init();
