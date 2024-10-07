// Create an audio context
const audioContext = new (window.AudioContext || window.webkitAudioContext)();
let audioBuffer1, audioBuffer2;
let source1, source2;
let progressInterval;

let startTime;
let pausedAt = 0;
let isPlaying = false;

// Load sounds and show play button when ready
async function loadSounds() {
	const response = await wp.apiFetch({ path: '/pos/v1/ai-podcast/generate', method: 'POST' });

	console.log( 'Generated podcast, loading sounds', response);
	const sound1 = await fetch(response.soundtrack_url).then(response => response.arrayBuffer());
	const sound2 = await fetch(response.media_url).then(response => response.arrayBuffer());

	audioBuffer1 = await audioContext.decodeAudioData(sound1);
	audioBuffer2 = await audioContext.decodeAudioData(sound2);

	// Show play button once audio is loaded
	document.getElementById('playButton').style.display = 'inline-block';
	document.getElementById('progressBar').style.display = 'block';
	document.getElementById('hype-player').style.display = 'block';
	document.getElementById('player-loader').style.display = 'none';
}

// Play the loaded sounds
function playSounds() {
	if (isPlaying) return;

	source1 = audioContext.createBufferSource();
	source2 = audioContext.createBufferSource();

	source1.buffer = audioBuffer1;
	source2.buffer = audioBuffer2;

	const gainNode1 = audioContext.createGain();
	gainNode1.gain.setValueAtTime(0.2, audioContext.currentTime);

	source1.connect(gainNode1);
	gainNode1.connect(audioContext.destination);
	source2.connect(audioContext.destination);

	startTime = audioContext.currentTime - pausedAt;
	source1.start(0, pausedAt);
	source2.start(0, pausedAt);

	updateProgressBar();

	source2.onended = stopPlayback;

	isPlaying = true;
	document.getElementById('playButton').textContent = 'Resume';
	document.getElementById('pauseButton').style.display = 'inline-block';
}

// Add pauseSounds function
function pauseSounds() {
	if (!isPlaying) return;

	source1.stop();
	source2.stop();
	pausedAt = audioContext.currentTime - startTime;
	clearInterval(progressInterval);
	isPlaying = false;
	document.getElementById('playButton').textContent = 'Resume';
}

// Update progress bar
function updateProgressBar() {
	const progressElement = document.getElementById('progress');
	const duration = audioBuffer2.duration;

	progressInterval = setInterval(() => {
		const elapsedTime = audioContext.currentTime - startTime;
		const progress = (elapsedTime / duration) * 100;
		progressElement.style.width = `${Math.min(progress, 100)}%`;

		if (progress >= 100) {
			clearInterval(progressInterval);
		}
	}, 100);
}

// Stop playback
function stopPlayback() {
	if (source1) {
		source1.stop();
	}
	if (source2) {
		source2.stop();
	}
	clearInterval(progressInterval);
	document.getElementById('progress').style.width = '100%';
	isPlaying = false;
	pausedAt = 0;
	document.getElementById('playButton').textContent = 'Play Audio';
	document.getElementById('pauseButton').style.display = 'none';
}

// Load sounds when the page loads
window.addEventListener('load', loadSounds);

// Add event listeners to buttons
document.addEventListener('DOMContentLoaded', function() {
	document.getElementById('playButton').addEventListener('click', playSounds);
	document.getElementById('pauseButton').addEventListener('click', pauseSounds);
});