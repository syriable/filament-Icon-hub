/**
 * Filament Icon Hub – icon picker Alpine component.
 *
 * Hand-written ES module (no build step). Loaded on demand through
 * Filament's x-load. All icon data comes from the field's exposed
 * `searchIcons` method, one page per request.
 */
export default function iconHubPicker({
    state,
    componentKey,
    modalId,
    isMultiple,
    providers,
    showProviderFilter,
    messages,
}) {
    const emptyFilters = () => ({ categories: [], variants: [] })

    return {
        state,
        isMultiple,
        providers,
        showProviderFilter,
        selected: {},

        search: '',
        provider: '',
        category: '',
        variant: '',
        filters: emptyFilters(),
        filtersCache: {},

        icons: [],
        next: null,
        errors: [],

        isLoading: false,
        isLoadingMore: false,
        isSearching: false,
        hasLoaded: false,

        activeIndex: 0,
        requestId: 0,
        announcement: '',
        messages: messages ?? {},

        observer: null,
        openListener: null,

        init() {
            try {
                this.selected = JSON.parse(this.$refs.selectedIcons?.textContent || '{}')
            } catch (error) {
                this.selected = {}
            }

            if (this.providers.length === 1) {
                this.provider = this.providers[0].id
            }

            this.$watch('state', () => this.syncSelected())

            this.openListener = (event) => {
                if (event.detail?.id === modalId) {
                    this.onOpen()
                }
            }

            window.addEventListener('open-modal', this.openListener)
        },

        destroy() {
            window.removeEventListener('open-modal', this.openListener)
            this.observer?.disconnect()
        },

        open() {
            this.$dispatch('open-modal', { id: modalId })
        },

        close() {
            this.$dispatch('close-modal', { id: modalId })
        },

        onOpen() {
            if (! this.hasLoaded) {
                this.reload()
            }

            this.$nextTick(() => {
                this.observe()

                if (this.$refs.search) {
                    this.$refs.search.focus()
                } else {
                    this.focusGrid()
                }
            })
        },

        // Selection -------------------------------------------------------

        ids() {
            if (Array.isArray(this.state)) {
                return this.state.filter((id) => typeof id === 'string' && id !== '')
            }

            return typeof this.state === 'string' && this.state !== '' ? [this.state] : []
        },

        selectedList() {
            return this.ids().map((id) => this.selected[id] ?? { id, label: id, html: '' })
        },

        isSelected(icon) {
            return this.ids().includes(icon.id)
        },

        remember(icon) {
            this.selected = { ...this.selected, [icon.id]: icon }
        },

        choose(icon, index = null) {
            if (index !== null) {
                this.activeIndex = index
            }

            if (this.isMultiple) {
                this.toggle(icon)

                return
            }

            this.remember(icon)
            this.state = icon.id
            this.close()
        },

        toggle(icon) {
            const ids = this.ids()

            if (ids.includes(icon.id)) {
                this.state = ids.filter((id) => id !== icon.id)

                return
            }

            this.remember(icon)
            this.state = [...ids, icon.id]
        },

        clear() {
            this.state = this.isMultiple ? [] : null
        },

        async syncSelected() {
            const missing = this.ids().filter((id) => ! this.selected[id] || this.selected[id].html === '')

            if (! missing.length) {
                return
            }

            try {
                const icons = await this.$wire.callSchemaComponentMethod(componentKey, 'getIconsForJs', { ids: missing })

                this.selected = { ...this.selected, ...(Array.isArray(icons) ? {} : icons) }
            } catch (error) {
                // Previews are cosmetic; the stored value is unaffected.
            }
        },

        selectedCountLabel() {
            return (this.messages.selectedCount ?? ':count').replace(':count', this.ids().length)
        },

        // Loading ---------------------------------------------------------

        changeProvider() {
            this.category = ''
            this.variant = ''
            this.filters = this.filtersCache[this.provider] ?? emptyFilters()
            this.reload()
        },

        async reload() {
            this.next = null
            this.activeIndex = 0
            await this.fetch(false)
        },

        async loadMore() {
            if (! this.next || this.isLoading || this.isLoadingMore) {
                return
            }

            await this.fetch(true)
        },

        async fetch(append) {
            const requestId = ++this.requestId
            const provider = this.provider
            const withFilters = provider !== '' && ! (provider in this.filtersCache)

            if (append) {
                this.isLoadingMore = true
            } else {
                this.isLoading = true
                this.isSearching = this.search.trim() !== ''
            }

            try {
                const response = await this.$wire.callSchemaComponentMethod(componentKey, 'searchIcons', {
                    search: this.search,
                    provider: provider || null,
                    category: this.category || null,
                    variant: this.variant || null,
                    cursor: append ? this.next : null,
                    withFilters,
                })

                if (requestId !== this.requestId) {
                    return
                }

                if (withFilters && response?.filters) {
                    this.filtersCache[provider] = response.filters
                }

                this.filters = provider ? (this.filtersCache[provider] ?? emptyFilters()) : emptyFilters()

                const incoming = response?.icons ?? []

                if (append) {
                    const known = new Set(this.icons.map((icon) => icon.id))
                    this.icons = this.icons.concat(incoming.filter((icon) => ! known.has(icon.id)))
                } else {
                    this.icons = incoming
                }

                this.next = response?.next ?? null
                this.errors = response?.errors ?? []
                this.hasLoaded = true
                this.announcement = (this.messages.loaded ?? ':count').replace(':count', this.icons.length)
            } catch (error) {
                if (requestId === this.requestId) {
                    this.next = null
                    this.errors = [{ provider: '_request', label: '', message: this.messages.loadFailed ?? '' }]
                }
            } finally {
                if (requestId === this.requestId) {
                    this.isLoading = false
                    this.isLoadingMore = false
                    this.isSearching = false

                    this.$nextTick(() => this.fillViewport())
                }
            }
        },

        observe() {
            if (this.observer || ! this.$refs.sentinel || ! ('IntersectionObserver' in window)) {
                return
            }

            this.observer = new IntersectionObserver(
                (entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        this.loadMore()
                    }
                },
                { root: this.$refs.scroll, rootMargin: '0px 0px 240px 0px' },
            )

            this.observer.observe(this.$refs.sentinel)
        },

        // The observer only fires on changes; if a page does not fill the
        // viewport the sentinel stays visible, so check once after loading.
        fillViewport() {
            const scroll = this.$refs.scroll
            const sentinel = this.$refs.sentinel

            if (! this.next || ! scroll || ! sentinel || scroll.offsetParent === null) {
                return
            }

            if (sentinel.getBoundingClientRect().top <= scroll.getBoundingClientRect().bottom + 240) {
                this.loadMore()
            }
        },

        emptyHeading() {
            if (! this.providers.length) {
                return this.messages.noProviders
            }

            if (this.errors.length && this.provider) {
                return this.messages.providerUnavailable
            }

            if (this.search.trim() !== '') {
                return this.messages.noResults
            }

            return this.messages.noIcons
        },

        // Keyboard navigation (roving tabindex) -----------------------------

        optionId(index) {
            return `${modalId}-option-${index}`
        },

        options() {
            return this.$refs.grid ? Array.from(this.$refs.grid.querySelectorAll('[role="option"]')) : []
        },

        focusGrid() {
            if (this.icons.length) {
                this.focusItem(Math.min(this.activeIndex, this.icons.length - 1))
            }
        },

        focusItem(index) {
            const options = this.options()

            if (! options.length) {
                return
            }

            this.activeIndex = Math.max(0, Math.min(index, options.length - 1))

            const option = options[this.activeIndex]
            option.focus({ preventScroll: true })
            option.scrollIntoView({ block: 'nearest' })

            if (this.activeIndex >= options.length - this.columns() * 2) {
                this.loadMore()
            }
        },

        columns() {
            const options = this.options()

            if (options.length < 2) {
                return 1
            }

            const top = options[0].offsetTop
            const index = options.findIndex((option) => option.offsetTop !== top)

            return index === -1 ? options.length : index
        },

        onGridKeydown(event) {
            const isRtl = getComputedStyle(this.$refs.grid).direction === 'rtl'
            const columns = this.columns()
            const rows = Math.max(1, Math.floor((this.$refs.scroll?.clientHeight ?? 0) / 64))

            const moves = {
                ArrowRight: isRtl ? -1 : 1,
                ArrowLeft: isRtl ? 1 : -1,
                ArrowDown: columns,
                ArrowUp: -columns,
                PageDown: columns * rows,
                PageUp: -columns * rows,
            }

            if (event.key in moves) {
                event.preventDefault()

                if (event.key === 'ArrowUp' && this.activeIndex < columns && this.$refs.search) {
                    this.$refs.search.focus()

                    return
                }

                this.focusItem(this.activeIndex + moves[event.key])

                return
            }

            if (event.key === 'Home') {
                event.preventDefault()
                this.focusItem(0)

                return
            }

            if (event.key === 'End') {
                event.preventDefault()
                this.focusItem(this.icons.length - 1)

                return
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()

                const icon = this.icons[this.activeIndex]

                if (icon) {
                    this.choose(icon, this.activeIndex)
                }
            }
        },
    }
}
