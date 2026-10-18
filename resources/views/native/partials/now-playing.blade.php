{{--
    Music card for the Speed screen.
    @param array|null $nowPlaying  from SpeedTracker::nowPlaying()
    @param bool $isHud             large white-on-black variant
    @param bool $isAndroid         music controls only exist on Android
--}}
@use('App\Icons\Ios')
@use('App\Icons\Android')

@if ($nowPlaying && ! $nowPlaying['available'] && ! $isHud && $isAndroid)
    <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
        <row class="items-center gap-2">
            <icon :ios="Ios::MusicNote" :android="Android::MusicNote" :size="18" class="text-theme-on-surface-variant" />
            <text class="text-sm font-bold text-theme-on-surface">Music controls unavailable</text>
        </row>
        <text class="text-xs text-theme-on-surface-variant">This build doesn't include them. Install the full Tripometer app (native:run or a GitHub release). They don't work in NativePHP Jump or older builds.</text>
    </column>
@elseif ($nowPlaying && $nowPlaying['supported'])
    @if (isset($nowPlaying['error']))
        @unless ($isHud)
            <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-sm font-bold text-theme-on-surface">Music controls error</text>
                <text class="text-xs text-theme-destructive">{{ $nowPlaying['error'] }}</text>
            </column>
        @endunless
    @elseif (! $nowPlaying['access'])
        @unless ($isHud)
            <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
                <row class="items-center gap-2">
                    <icon :ios="Ios::MusicNote" :android="Android::MusicNote" :size="18" class="text-theme-on-surface" />
                    <text class="text-sm font-bold text-theme-on-surface">Show what's playing</text>
                </row>
                <text class="text-xs text-theme-on-surface-variant">See and control Spotify, YouTube Music or any music app. Android calls this "Notification access"; Tripometer only uses it for media controls.</text>
                <button variant="ghost" @press="grantMediaAccess" class="w-full">Allow access</button>
            </column>
        @endunless
    @elseif ($nowPlaying['active'])
        <column class="w-full gap-2 {{ $isHud ? '' : 'rounded-2xl bg-theme-surface p-4' }}" a11y-label="Now playing: {{ $nowPlaying['title'] }} by {{ $nowPlaying['artist'] }}">
            <column class="w-full {{ $isHud ? 'items-center' : '' }}">
                <text class="{{ $isHud ? 'text-2xl text-white text-center' : 'text-sm text-theme-on-surface' }} font-bold" maxLines="1">{{ $nowPlaying['title'] ?: 'Unknown track' }}</text>
                <text class="{{ $isHud ? 'text-lg text-zinc-400 text-center' : 'text-xs text-theme-on-surface-variant' }}" maxLines="1">{{ collect([$nowPlaying['artist'], $nowPlaying['app']])->filter()->implode(' · ') }}</text>
            </column>
            <row class="w-full items-center justify-center {{ $isHud ? 'gap-6' : 'gap-3' }}">
                <button variant="ghost" :ios-icon="Ios::SpeakerMinusFill" :android-icon="Android::VolumeDown" @press="adjustVolume('down')" a11y-label="Volume down" />
                <button variant="ghost" :ios-icon="Ios::BackwardFill" :android-icon="Android::SkipPrevious" @press="mediaCommand('previous')" a11y-label="Previous track" />
                <button variant="{{ $isHud ? 'ghost' : 'primary' }}" :ios-icon="$nowPlaying['playing'] ? Ios::PauseFill : Ios::PlayFill" :android-icon="$nowPlaying['playing'] ? Android::Pause : Android::PlayArrow" @press="mediaCommand('toggle')" a11y-label="{{ $nowPlaying['playing'] ? 'Pause' : 'Play' }}" />
                <button variant="ghost" :ios-icon="Ios::ForwardFill" :android-icon="Android::SkipNext" @press="mediaCommand('next')" a11y-label="Next track" />
                <button variant="ghost" :ios-icon="Ios::SpeakerPlusFill" :android-icon="Android::VolumeUp" @press="adjustVolume('up')" a11y-label="Volume up" />
            </row>
        </column>
    @endif
@endif
