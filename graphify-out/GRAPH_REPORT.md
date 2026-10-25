# Graph Report - seat-reservation  (2026-10-25)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 7575 nodes · 17039 edges · 233 communities (163 shown, 70 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 1003 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `2264805f`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 155
- Community 156
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 170
- Community 171
- Community 172
- Community 174
- Community 175
- Community 176
- Community 177
- Community 178
- Community 179
- Community 180
- Community 181
- Community 182
- Community 183
- Community 185
- Community 186
- Community 187
- Community 188
- Community 189
- Community 190
- Community 191
- Community 192
- Community 193
- Community 194
- Community 195
- Community 196
- Community 197
- Community 198
- Community 199
- Community 200
- Community 201
- Community 202
- Community 213
- Community 214
- Community 215

## God Nodes (most connected - your core abstractions)
1. `vue` - 278 edges
2. `cn()` - 235 edges
3. `reka-ui` - 219 edges
4. `@vueuse/core` - 158 edges
5. `constructor()` - 98 edges
6. `update()` - 94 edges
7. `BookingImportTest` - 90 edges
8. `_update()` - 78 edges
9. `_update()` - 76 edges
10. `Event` - 64 edges

## Surprising Connections (you probably didn't know these)
- `upcomingEvents` --calls--> `dayjs`  [INFERRED]
  resources/js/Admin/Layouts/AdminLayout.vue → package.json
- `getEventStatus()` --calls--> `dayjs`  [INFERRED]
  resources/js/Pages/Admin/EventIndex.vue → package.json
- `getBookingStatus()` --calls--> `dayjs`  [INFERRED]
  resources/js/Pages/Booking/IndexBooking.vue → package.json
- `abbreviation` --calls--> `dayjs`  [INFERRED]
  resources/js/Components/TimezoneNote.vue → package.json
- `registrationStatus` --calls--> `dayjs`  [INFERRED]
  resources/js/Pages/Admin/EventShow.vue → package.json

## Import Cycles
- 3-file cycle: `resources/js/Components/ui/sidebar/SidebarMenuButton.vue -> resources/js/Components/ui/sidebar/SidebarMenuButtonChild.vue -> resources/js/Components/ui/sidebar/index.ts -> resources/js/Components/ui/sidebar/SidebarMenuButton.vue`
- 3-file cycle: `resources/js/Components/ui/auto-form/AutoFormField.vue -> resources/js/Components/ui/auto-form/constant.ts -> resources/js/Components/ui/auto-form/AutoFormFieldObject.vue -> resources/js/Components/ui/auto-form/AutoFormField.vue`
- 3-file cycle: `resources/js/Components/ui/auto-form/AutoFormField.vue -> resources/js/Components/ui/auto-form/constant.ts -> resources/js/Components/ui/auto-form/AutoFormFieldArray.vue -> resources/js/Components/ui/auto-form/AutoFormField.vue`

## Communities (233 total, 70 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.01
Nodes (84): addChanges(), addMapping(), Ah(), annotation(), as(), AX(), b1(), baseTheme() (+76 more)

### Community 1 - "Community 1"
Cohesion: 0.01
Nodes (121): acquireContext(), add(), addControllers(), addPlugins(), addScales(), ah(), _animateOptions(), bg() (+113 more)

### Community 2 - "Community 2"
Cohesion: 0.01
Nodes (102): fQ(), addHackNode(), addKeyboardShortcuts(), addStoredMark(), addTextblockHacks(), addToSet(), addTransform(), allowedMarks() (+94 more)

### Community 3 - "Community 3"
Cohesion: 0.02
Nodes (82): acquireContext(), add(), addControllers(), addPlugins(), addScales(), _animateOptions(), as(), bc() (+74 more)

### Community 4 - "Community 4"
Cohesion: 0.03
Nodes (73): props, props, props, props, props, props, props, props (+65 more)

### Community 5 - "Community 5"
Cohesion: 0.02
Nodes (88): reka-ui, props, props, delegatedProps, props, props, emits, forwarded (+80 more)

### Community 6 - "Community 6"
Cohesion: 0.03
Nodes (111): accept(), addInfoPane(), addSelection(), applyEdits(), buildDeco(), checkAsyncSchedule(), checkHover(), chunkEnd() (+103 more)

### Community 7 - "Community 7"
Cohesion: 0.03
Nodes (21): AdminController, AutoPlaceSeatsController, DeleteBookingController, EventAdminController, GlobalImportTemplateController, ImportPreviewController, ImportTemplateController, ManualBookingController (+13 more)

### Community 8 - "Community 8"
Cohesion: 0.07
Nodes (94): fromJSON(), markers(), addBadgesForSelectedOptions(), addSingleBadge(), addSingleSelectionDisplay(), applyDisabledState(), At(), B() (+86 more)

### Community 9 - "Community 9"
Cohesion: 0.06
Nodes (60): vee-validate, zod, { dependencies }, emits, fields, formComponent, formComponentProps, props (+52 more)

### Community 10 - "Community 10"
Cohesion: 0.07
Nodes (92): A(), addBadgesForSelectedOptions(), addSingleBadge(), addSingleSelectionDisplay(), applyDisabledState(), At(), B(), be() (+84 more)

### Community 11 - "Community 11"
Cohesion: 0.03
Nodes (14): AdminDashboardTest, BookingManagementTest, AuthFlowTest, LoginCsrfTest, BookingCodeGenerationTest, BookingCodeIntegrationTest, EventBookingWindowUpdateTest, FlashMessageTest (+6 more)

### Community 12 - "Community 12"
Cohesion: 0.04
Nodes (14): {closure#1}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#3}(), {closure#4}(), {closure#5}() (+6 more)

### Community 13 - "Community 13"
Cohesion: 0.03
Nodes (88): zh(), alpha(), An(), apply(), Ar(), bh(), Bt(), ch() (+80 more)

### Community 15 - "Community 15"
Cohesion: 0.04
Nodes (87): aa(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterFit(), afterSetDimensions(), afterTickToLabelConversion(), afterUpdate() (+79 more)

### Community 16 - "Community 16"
Cohesion: 0.04
Nodes (86): ac(), adjustHitBoxes(), ae(), af(), at(), bd(), beforeDatasetDraw(), beforeDatasetsDraw() (+78 more)

### Community 17 - "Community 17"
Cohesion: 0.03
Nodes (58): @vueuse/core, delegatedProps, forwarded, props, delegatedProps, props, delegatedProps, emits (+50 more)

### Community 18 - "Community 18"
Cohesion: 0.04
Nodes (77): acceptToken(), add(), addInner(), adjust(), Ba(), balance(), bn(), compare() (+69 more)

### Community 19 - "Community 19"
Cohesion: 0.04
Nodes (79): afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterFit(), afterSetDimensions(), afterTickToLabelConversion(), afterUpdate(), beforeBuildTicks() (+71 more)

### Community 20 - "Community 20"
Cohesion: 0.04
Nodes (78): VariableDefinition(), [x](), addCommands(), addNodeView(), Af(), apply(), ax(), c() (+70 more)

### Community 21 - "Community 21"
Cohesion: 0.04
Nodes (66): dependencies, embla-carousel-vue@^8.6.0, lucide-vue-next@^0.536.0, @panzoom/panzoom@^4.6.0, pinia@^3.0.3, @radix-ui/colors@^3.0.0, radix-vue@^1.9.17, reka-ui@^2.4.1 (+58 more)

### Community 22 - "Community 22"
Cohesion: 0.05
Nodes (55): Ht(), QQ(), be(), c(), clickPercent(), constructor(), de(), define() (+47 more)

### Community 23 - "Community 23"
Cohesion: 0.04
Nodes (71): slot(), $a(), ao(), Bc(), Bs(), configure(), constructor(), createCommandManager() (+63 more)

### Community 24 - "Community 24"
Cohesion: 0.04
Nodes (40): dayjs@^1.11.10, dayjs, date, emit, parseDateTime(), parseTime(), Props, time (+32 more)

### Community 25 - "Community 25"
Cohesion: 0.04
Nodes (71): Tg(), Am(), bc(), Ct(), daysInMonth(), de(), defaultZone(), eu() (+63 more)

### Community 26 - "Community 26"
Cohesion: 0.04
Nodes (69): addGaps(), af(), ag(), Ap(), At(), be(), bm(), n() (+61 more)

### Community 27 - "Community 27"
Cohesion: 0.04
Nodes (48): lucide-vue-next, delegatedProps, props, delegatedProps, props, props, Props, ButtonVariants (+40 more)

### Community 28 - "Community 28"
Cohesion: 0.04
Nodes (63): readOnly(), tooltip(), ac(), am(), atEnd(), atStart(), cc(), Cf() (+55 more)

### Community 30 - "Community 30"
Cohesion: 0.05
Nodes (63): addMark(), as(), au(), ba(), coordsAtPos(), Dc(), delete(), descendants() (+55 more)

### Community 31 - "Community 31"
Cohesion: 0.07
Nodes (61): Image(), Link(), Ah(), ai(), An(), bh(), br(), bx() (+53 more)

### Community 32 - "Community 32"
Cohesion: 0.05
Nodes (8): BookingCsvExporter, ExportAllBookingsController, ExportBookingsController, Booking, BookingPolicy, BookingSecurityTest, BookingUnitTest, SeatAssignerTest

### Community 33 - "Community 33"
Cohesion: 0.06
Nodes (60): A(), applyStack(), aspectRatio(), bl(), _calculateBarIndexPixels(), _calculateBarValuePixels(), configure(), countVisibleElements() (+52 more)

### Community 34 - "Community 34"
Cohesion: 0.03
Nodes (44): delegatedProps, emits, forwarded, props, delegatedProps, emits, forwarded, props (+36 more)

### Community 35 - "Community 35"
Cohesion: 0.05
Nodes (35): vue, props, props, props, props, props, props, props (+27 more)

### Community 36 - "Community 36"
Cohesion: 0.05
Nodes (58): ac(), addEventListener(), al(), apply(), bs(), ci(), cl(), cn() (+50 more)

### Community 37 - "Community 37"
Cohesion: 0.06
Nodes (57): Ae(), afterAutoSkip(), bo(), buildLookupTable(), buildTicks(), calculateLabelRotation(), _calculatePadding(), _computeGridLineItems() (+49 more)

### Community 38 - "Community 38"
Cohesion: 0.05
Nodes (33): isProcessing, open, props, form, nameChanged, open, originalName, props (+25 more)

### Community 39 - "Community 39"
Cohesion: 0.04
Nodes (40): emits, forwarded, props, delegatedProps, emits, forwarded, props, delegatedProps (+32 more)

### Community 40 - "Community 40"
Cohesion: 0.04
Nodes (37): applyBlockIdMap(), BlockPayload, canConfirmDelete, CellType, collapsedPanels, confirmDeleteBookingsAndSave(), deleteBookingsConfirmText, ENTRANCE_DIRECTIONS (+29 more)

### Community 41 - "Community 41"
Cohesion: 0.04
Nodes (39): allGroups, allItems, { contains }, delegatedProps, emits, filterState, forwarded, props (+31 more)

### Community 42 - "Community 42"
Cohesion: 0.04
Nodes (53): applyTransaction(), aQ(), B(), between(), build(), Cg(), n(), Ef() (+45 more)

### Community 43 - "Community 43"
Cohesion: 0.11
Nodes (3): MasterCardSvgGenerator, OrderCardSvgGenerator, SvgUtilities

### Community 44 - "Community 44"
Cohesion: 0.05
Nodes (52): _a(), Ab(), am(), ATXHeading(), ay(), Bl(), BP(), BulletList() (+44 more)

### Community 45 - "Community 45"
Cohesion: 0.05
Nodes (52): Og(), addInputRules(), addPasteRules(), ap(), at(), bg(), bu(), Ce() (+44 more)

### Community 46 - "Community 46"
Cohesion: 0.05
Nodes (27): actions(), button(), close(), configureAnimations(), configureTransitions(), constructor(), danger(), dispatch() (+19 more)

### Community 47 - "Community 47"
Cohesion: 0.09
Nodes (12): EventMatcher, {closure#1}(), GlobalImportSession, ImportBookingWriter, ImportCsvParser, ImportProposalBuilder, ImportSessionStore, {closure#4}() (+4 more)

### Community 48 - "Community 48"
Cohesion: 0.06
Nodes (33): info, props, getBookerName(), getBookerType(), getGuestName(), getSeatInfo(), debouncedSearch(), deleteOpen (+25 more)

### Community 49 - "Community 49"
Cohesion: 0.07
Nodes (20): activeIndex, expandedIndices, ImportGuest, isCollapsedAlreadyBooked(), isCollapsible(), props, renamePending(), selectGuest() (+12 more)

### Community 50 - "Community 50"
Cohesion: 0.04
Nodes (3): AdminControllerTest, RoomAdminControllerTest, RoomLayoutController2Test

### Community 52 - "Community 52"
Cohesion: 0.06
Nodes (45): addWindowListeners(), atLastNode(), child(), childAfter(), childBefore(), cursor(), destroy(), Dr() (+37 more)

### Community 53 - "Community 53"
Cohesion: 0.07
Nodes (46): j0(), KQ(), wl(), isEmpty(), K0(), Me(), Nt(), q0() (+38 more)

### Community 54 - "Community 54"
Cohesion: 0.08
Nodes (46): adjustHitBoxes(), At(), beforeDatasetDraw(), beforeDatasetsDraw(), beforeDraw(), bi(), co(), _computeLabelArea() (+38 more)

### Community 55 - "Community 55"
Cohesion: 0.06
Nodes (45): addBlock(), addBlockWidget(), addChunk(), addLeafElement(), addLineDeco(), addNode(), advance(), advanceFully() (+37 more)

### Community 56 - "Community 56"
Cohesion: 0.04
Nodes (24): delegatedProps, emits, forwarded, props, delegatedProps, forwardedProps, props, delegatedProps (+16 more)

### Community 57 - "Community 57"
Cohesion: 0.04
Nodes (24): delegatedProps, emits, forwarded, props, delegatedProps, forwardedProps, props, delegatedProps (+16 more)

### Community 58 - "Community 58"
Cohesion: 0.07
Nodes (44): afterAutoSkip(), As(), buildLookupTable(), buildTicks(), calculateCircumference(), calculateLabelRotation(), _calculatePadding(), cf() (+36 more)

### Community 59 - "Community 59"
Cohesion: 0.06
Nodes (43): getType(), afterDatasetsUpdate(), ao(), bm(), bo(), El(), fm(), gd() (+35 more)

### Community 60 - "Community 60"
Cohesion: 0.06
Nodes (43): au(), ca(), da(), dataset(), l(), $e(), Ei(), fc() (+35 more)

### Community 61 - "Community 61"
Cohesion: 0.06
Nodes (30): SidebarMenuButtonVariants, delegatedProps, { isMobile, state }, props, props, SidebarMenuButtonProps, emits, isMobile (+22 more)

### Community 62 - "Community 62"
Cohesion: 0.05
Nodes (41): WWWGROUP, args, context, dockerfile, IGNITION_LOCAL_SITES_PATH, LARAVEL_SAIL, MYSQL_ALLOW_EMPTY_PASSWORD, MYSQL_DATABASE (+33 more)

### Community 63 - "Community 63"
Cohesion: 0.05
Nodes (42): ag(), $c(), cc(), Cs(), diff(), diffNow(), endOf(), equals() (+34 more)

### Community 64 - "Community 64"
Cohesion: 0.14
Nodes (4): SeatAssigner, {closure#7}(), RoomLayoutController, Room

### Community 65 - "Community 65"
Cohesion: 0.05
Nodes (40): bv(), c1(), cu(), d1(), Ed(), Eu(), findRegions(), Gu() (+32 more)

### Community 66 - "Community 66"
Cohesion: 0.07
Nodes (23): @inertiajs/vue3, page, props, sidebarOpen, upcomingEvents, attrs, props, FlashMessages (+15 more)

### Community 68 - "Community 68"
Cohesion: 0.12
Nodes (37): v(), De(), Ft(), ir(), be(), ce(), de(), Dt() (+29 more)

### Community 69 - "Community 69"
Cohesion: 0.07
Nodes (38): addAttributes(), after(), before(), blockRange(), check(), connectSelection(), ct(), doc() (+30 more)

### Community 70 - "Community 70"
Cohesion: 0.05
Nodes (25): emits, forwarded, props, delegatedProps, emits, forwarded, props, props (+17 more)

### Community 71 - "Community 71"
Cohesion: 0.07
Nodes (38): an(), become(), bf(), bidiSpans(), childCursor(), childPos(), compositionView(), coordsAtPos() (+30 more)

### Community 72 - "Community 72"
Cohesion: 0.09
Nodes (38): applyStack(), _calculateBarIndexPixels(), _calculateBarValuePixels(), ch(), _computeAngle(), countVisibleElements(), D(), Fn() (+30 more)

### Community 74 - "Community 74"
Cohesion: 0.07
Nodes (7): BlockFactory, BookingFactory, EventFactory, RoomFactory, RowFactory, SeatFactory, UserFactory

### Community 75 - "Community 75"
Cohesion: 0.07
Nodes (7): Kernel, EncryptCookies, PreventRequestsDuringMaintenance, TrimStrings, TrustProxies, ValidateSignature, VerifyCsrfToken

### Community 76 - "Community 76"
Cohesion: 0.07
Nodes (36): Ac(), addElement(), applyChanges(), balanced(), baseIndent(), baseIndentFor(), blockAt(), childString() (+28 more)

### Community 77 - "Community 77"
Cohesion: 0.06
Nodes (27): navigationMenuTriggerStyle, delegatedProps, emits, forwarded, props, delegatedProps, emits, forwarded (+19 more)

### Community 78 - "Community 78"
Cohesion: 0.08
Nodes (34): addDelimiter(), after(), append(), before(), Bg(), dO(), ei(), ff() (+26 more)

### Community 79 - "Community 79"
Cohesion: 0.07
Nodes (34): Bs(), changeByRange(), changes(), createParse(), Di(), dP(), eg(), o() (+26 more)

### Community 80 - "Community 80"
Cohesion: 0.13
Nodes (31): selectedIndex(), Be(), _e(), er(), et(), Fe(), G(), Ge() (+23 more)

### Community 81 - "Community 81"
Cohesion: 0.06
Nodes (23): emits, forwarded, props, props, delegatedProps, emits, forwarded, props (+15 more)

### Community 82 - "Community 82"
Cohesion: 0.14
Nodes (4): RoomLayoutLoader, BookingController, {closure#1}(), Event

### Community 83 - "Community 83"
Cohesion: 0.06
Nodes (33): cf(), ad(), addOptions(), addProseMirrorPlugins(), buildProps(), can(), chain(), cl() (+25 more)

### Community 84 - "Community 84"
Cohesion: 0.08
Nodes (27): $c(), cm(), De(), Fr(), Gn(), hn(), ja(), jc() (+19 more)

### Community 85 - "Community 85"
Cohesion: 0.07
Nodes (4): SeatingCardsController, {closure#1}(), HandleInertiaRequests, SeatingCardsTest

### Community 86 - "Community 86"
Cohesion: 0.11
Nodes (27): B(), C(), close(), F(), G(), H(), init(), J() (+19 more)

### Community 87 - "Community 87"
Cohesion: 0.08
Nodes (17): ar(), b(), Be(), cr(), g(), H(), ji(), Me() (+9 more)

### Community 88 - "Community 88"
Cohesion: 0.08
Nodes (31): BO(), clear(), clearDelayedAndroidKey(), Dc(), delayAndroidKey(), enforceCursorAssoc(), flush(), flushIOSKey() (+23 more)

### Community 89 - "Community 89"
Cohesion: 0.12
Nodes (22): embla-carousel-vue, { canScrollNext, canScrollPrev, carouselApi, carouselRef, orientation, scrollNext, scrollPrev }, emits, props, { carouselRef, orientation }, props, { orientation }, props (+14 more)

### Community 90 - "Community 90"
Cohesion: 0.09
Nodes (31): _a(), aa(), alpha(), ba(), br(), ca(), da(), fa() (+23 more)

### Community 91 - "Community 91"
Cohesion: 0.08
Nodes (31): an(), color(), dc(), determineDataLimits(), dh(), ee(), Ei(), En() (+23 more)

### Community 92 - "Community 92"
Cohesion: 0.08
Nodes (29): needs, runs-on, steps, jobs, build, laravel-tests, runs-on, steps (+21 more)

### Community 93 - "Community 93"
Cohesion: 0.07
Nodes (30): Ae(), cn(), dg(), done(), dp(), em(), fg(), Fl() (+22 more)

### Community 94 - "Community 94"
Cohesion: 0.09
Nodes (30): beforeLayout(), Cl(), determineDataLimits(), df(), dm(), ff(), Fl(), getAllParsedValues() (+22 more)

### Community 95 - "Community 95"
Cohesion: 0.08
Nodes (30): je(), zu(), bh(), getActiveElements(), getCenterPoint(), getElementsAtEventForMode(), _getLegendItemAt(), getProps() (+22 more)

### Community 96 - "Community 96"
Cohesion: 0.12
Nodes (4): CheckFullyBookedRooms, CheckReservationPeriods, TelegramNotificationService, TelegramNotificationServiceTest

### Community 97 - "Community 97"
Cohesion: 0.09
Nodes (18): vaul-vue@^0.4.1, vaul-vue, emits, forwarded, props, props, emits, forwarded (+10 more)

### Community 98 - "Community 98"
Cohesion: 0.11
Nodes (28): cc(), _computeLabelSizes(), El(), ga(), gh(), Je(), jr(), ka() (+20 more)

### Community 99 - "Community 99"
Cohesion: 0.09
Nodes (20): class-variance-authority@^0.7.1, class-variance-authority, delegatedProps, props, BadgeVariants, delegatedProps, emits, forwarded (+12 more)

### Community 100 - "Community 100"
Cohesion: 0.10
Nodes (3): AppServiceProvider, BroadcastServiceProvider, SocialiteIdentityProvider

### Community 101 - "Community 101"
Cohesion: 0.12
Nodes (26): addAll(), addDOM(), addElement(), addElementByRule(), addTextNode(), closeExtra(), currentPos(), enter() (+18 more)

### Community 102 - "Community 102"
Cohesion: 0.10
Nodes (25): apply(), Bw(), close(), emitContext(), emitLookAhead(), Ex(), findForcedReduction(), forceAll() (+17 more)

### Community 103 - "Community 103"
Cohesion: 0.16
Nodes (21): _activate(), _componentStrategy(), data(), _download(), _generateRequirements(), _getModule(), h(), _handleAlias() (+13 more)

### Community 104 - "Community 104"
Cohesion: 0.11
Nodes (23): Props, blockBounds, emit, GridCell, GridCellBlock, GridCellCovered, GridCellEntrance, GridCellNamed (+15 more)

### Community 105 - "Community 105"
Cohesion: 0.10
Nodes (24): ad(), addEventListener(), cd(), data(), ed(), fa(), getMaximumSize(), hc() (+16 more)

### Community 107 - "Community 107"
Cohesion: 0.13
Nodes (23): Kt(), add(), addInner(), computeAttrs(), content(), create(), createAndFill(), eu() (+15 more)

### Community 108 - "Community 108"
Cohesion: 0.21
Nodes (19): areRecordsSelected(), areRecordsToggleable(), canSelectAllRecords(), deselectAllRecords(), deselectRecords(), getRecordsOnPage(), getSelectedRecordsCount(), handleCheckboxClick() (+11 more)

### Community 109 - "Community 109"
Cohesion: 0.11
Nodes (23): Ar(), cr(), dataset(), di(), Ft(), getMaximumSize(), ht(), $i() (+15 more)

### Community 111 - "Community 111"
Cohesion: 0.10
Nodes (22): cs(), disconnectSelection(), flush(), flushSoon(), forceFlush(), Gc(), ignoreSelectionChange(), jm() (+14 more)

### Community 112 - "Community 112"
Cohesion: 0.12
Nodes (17): emit, getBlockNameClasses, getLayoutClasses, handleSeatClick(), handleSeatHover(), handleSeatLeave(), Alignment, FormBlock (+9 more)

### Community 113 - "Community 113"
Cohesion: 0.09
Nodes (14): emits, forwarded, props, delegatedProps, emits, forwarded, props, delegatedProps (+6 more)

### Community 114 - "Community 114"
Cohesion: 0.09
Nodes (16): delegatedProps, emits, forwarded, props, delegatedProps, forwardedProps, props, delegatedProps (+8 more)

### Community 115 - "Community 115"
Cohesion: 0.09
Nodes (21): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, jsxImportSource, lib (+13 more)

### Community 116 - "Community 116"
Cohesion: 0.13
Nodes (8): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}()

### Community 117 - "Community 117"
Cohesion: 0.15
Nodes (21): updateEditContextFormatting(), close(), closeFrontierNode(), dropNode(), Ei(), findCloseLevel(), findFittable(), fit() (+13 more)

### Community 118 - "Community 118"
Cohesion: 0.11
Nodes (19): addElements(), Al(), Bn(), buildOrUpdateElements(), clear(), _dataCheck(), _destroy(), ho() (+11 more)

### Community 119 - "Community 119"
Cohesion: 0.15
Nodes (19): addElements(), buildOrUpdateElements(), clear(), configure(), _dataCheck(), De(), _destroy(), ds() (+11 more)

### Community 120 - "Community 120"
Cohesion: 0.14
Nodes (10): @unovis/ts@^1.6.0, @unovis/vue@^1.6.0, @unovis/ts, @unovis/vue, props, wm, props, wm (+2 more)

### Community 121 - "Community 121"
Cohesion: 0.15
Nodes (18): addActive(), countIndent(), forward(), forward2(), forwardInner(), goto(), gotoInner(), ic() (+10 more)

### Community 122 - "Community 122"
Cohesion: 0.12
Nodes (16): aliases, components, composables, lib, ui, utils, iconLibrary, $schema (+8 more)

### Community 123 - "Community 123"
Cohesion: 0.15
Nodes (16): di(), e(), ei(), Ie(), ii(), le(), ni(), oi() (+8 more)

### Community 124 - "Community 124"
Cohesion: 0.12
Nodes (12): delegatedProps, emits, forwarded, props, delegatedProps, forwardedProps, props, forwardedProps (+4 more)

### Community 125 - "Community 125"
Cohesion: 0.12
Nodes (4): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 126 - "Community 126"
Cohesion: 0.13
Nodes (4): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 127 - "Community 127"
Cohesion: 0.14
Nodes (3): [g](), style(), update()

### Community 128 - "Community 128"
Cohesion: 0.13
Nodes (10): emits, forwarded, props, delegatedProps, props, delegatedProps, forwardedProps, props (+2 more)

### Community 129 - "Community 129"
Cohesion: 0.13
Nodes (11): delegatedProps, emits, forwarded, props, emits, forwarded, props, delegatedProps (+3 more)

### Community 130 - "Community 130"
Cohesion: 0.19
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), mergeAdjacentBlocks(), mergeRoomType(), roomIds(), splitSpannedBlocks()

### Community 131 - "Community 131"
Cohesion: 0.14
Nodes (14): Dm(), getCursor(), highlight(), Lm(), matchAll(), nextMatch(), nQ(), Oi() (+6 more)

### Community 132 - "Community 132"
Cohesion: 0.23
Nodes (14): Ae(), Bt(), ae(), A(), E(), at(), Gt(), i() (+6 more)

### Community 133 - "Community 133"
Cohesion: 0.22
Nodes (9): coerceInputValue(), commit(), composing, emits, inputRef, onCompositionEnd(), onInput(), props (+1 more)

### Community 134 - "Community 134"
Cohesion: 0.16
Nodes (8): cancel(), emit, Event, form, isEditMode, Props, roomLocked, submitForm()

### Community 135 - "Community 135"
Cohesion: 0.16
Nodes (11): chartRef, Data, emits, handleLegendItemClick(), index, isMounted, KeyOfT, legendItems (+3 more)

### Community 136 - "Community 136"
Cohesion: 0.16
Nodes (12): Data, emits, handleLegendItemClick(), index, isMounted, KeyOfT, legendItems, props (+4 more)

### Community 137 - "Community 137"
Cohesion: 0.15
Nodes (11): activeSegmentKey, category, Data, index, isMounted, KeyOfT, legendItems, props (+3 more)

### Community 138 - "Community 138"
Cohesion: 0.15
Nodes (12): secrets, uses, jobs, build-docker, branches, types, DockerHubPass, DockerHubPrefix (+4 more)

### Community 139 - "Community 139"
Cohesion: 0.19
Nodes (13): y(), C(), Ce(), Ct(), Et(), ie(), lt(), ot() (+5 more)

### Community 140 - "Community 140"
Cohesion: 0.18
Nodes (10): BaseChartProps, KeyOf, Data, emits, handleLegendItemClick(), index, isMounted, KeyOfT (+2 more)

### Community 141 - "Community 141"
Cohesion: 0.29
Nodes (13): blockRect(), canPlace(), canPlaceEntrance(), commitSpan(), entranceBand(), entranceRects(), getMarkerPlacementStatus(), handleCellClick() (+5 more)

### Community 142 - "Community 142"
Cohesion: 0.17
Nodes (12): require, guzzlehttp/guzzle, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/socialite, laravel/tinker, league/flysystem-aws-s3-v3 (+4 more)

### Community 144 - "Community 144"
Cohesion: 0.36
Nodes (9): alreadyConverted(), assertMysql(), assertTimezoneTablesLoaded(), assertWithinTimestampRange(), changeColumnTypes(), {closure#2}(), convertValues(), down() (+1 more)

### Community 146 - "Community 146"
Cohesion: 0.22
Nodes (11): addActions(), allActions(), getActions(), hasAction(), nextStates(), putAction(), stateFlag(), stateSlot() (+3 more)

### Community 147 - "Community 147"
Cohesion: 0.18
Nodes (11): ba(), Br(), du(), fu(), kt(), mu(), f(), $r() (+3 more)

### Community 148 - "Community 148"
Cohesion: 0.36
Nodes (9): blockRect(), canPlace(), others, clampSpan(), inGrid(), Rect, rectOnGrid(), rectsOverlap() (+1 more)

### Community 149 - "Community 149"
Cohesion: 0.20
Nodes (10): Ce(), De(), Dt(), Fe(), He(), ir(), Mt(), nr() (+2 more)

### Community 150 - "Community 150"
Cohesion: 0.31
Nodes (6): e(), f(), i(), o(), t(), u()

### Community 151 - "Community 151"
Cohesion: 0.27
Nodes (10): destroyBetween(), destroyRest(), findNodeMatch(), Hr(), isLocked(), matchesNode(), recreateWrapper(), syncToMarks() (+2 more)

### Community 152 - "Community 152"
Cohesion: 0.20
Nodes (7): delegatedProps, emits, forwarded, props, delegatedProps, forwardedProps, props

### Community 153 - "Community 153"
Cohesion: 0.27
Nodes (10): span(), blockFromProp(), inGrid(), layoutGrid, markerCells(), markerFromProp(), markerOrientation(), placeSpanned() (+2 more)

### Community 154 - "Community 154"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pint, laravel/sail, mockery/mockery, pestphp/pest, phpunit/phpunit (+1 more)

### Community 155 - "Community 155"
Cohesion: 0.31
Nodes (9): createDOM(), hf(), nf(), Pc(), reuseDOM(), setAttrs(), setDOM(), sync() (+1 more)

### Community 156 - "Community 156"
Cohesion: 0.22
Nodes (5): emits, forwarded, props, props, props

### Community 159 - "Community 159"
Cohesion: 0.25
Nodes (7): description, keywords, license, minimum-stability, name, prefer-stable, type

### Community 160 - "Community 160"
Cohesion: 0.25
Nodes (8): Bt(), eh(), hasValue(), ih(), mo(), po(), th(), _updateAnimationTarget()

### Community 162 - "Community 162"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 164 - "Community 164"
Cohesion: 0.29
Nodes (7): addNodeMark(), addStep(), maybeStep(), removeNodeMark(), setDocAttribute(), setNodeAttribute(), step()

### Community 165 - "Community 165"
Cohesion: 0.43
Nodes (7): cancel(), _createDescriptors(), _descriptors(), _notify(), _notifyStateChanges(), stop(), tick()

### Community 166 - "Community 166"
Cohesion: 0.38
Nodes (6): file, inputRef, isDragging, onChange(), onDrop(), setFile()

### Community 168 - "Community 168"
Cohesion: 0.33
Nodes (5): compilerOptions, baseUrl, paths, exclude, ziggy-js

### Community 169 - "Community 169"
Cohesion: 0.47
Nodes (5): elRef, emits, keepStyling(), onLegendItemClick(), props

### Community 170 - "Community 170"
Cohesion: 0.33
Nodes (6): addMarker(), addStageBlock(), closeNewBlockDialog(), createNewBlock(), nextTempKey(), rowsFromBlock()

### Community 171 - "Community 171"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 172 - "Community 172"
Cohesion: 0.40
Nodes (5): scripts, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd

### Community 186 - "Community 186"
Cohesion: 0.40
Nodes (5): DependencyType, DISABLES, HIDES, REQUIRES, SETS_OPTIONS

### Community 187 - "Community 187"
Cohesion: 0.40
Nodes (5): colors, colors, colors, defaultColors(), colors

### Community 197 - "Community 197"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 198 - "Community 198"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **7 isolated node(s):** `DISABLES`, `HIDES`, `REQUIRES`, `SETS_OPTIONS`, `pdf.card-layout` (+2 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 2614 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **70 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `La()` connect `Community 0` to `Community 3`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 9 inferred relationships involving `constructor()` (e.g. with `Ei()` and `io()`) actually correct?**
  _`constructor()` has 9 INFERRED edges - model-reasoned connections that need verification._
- **What connects `DISABLES`, `HIDES`, `REQUIRES` to the rest of the system?**
  _7 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.005803084592615672 - nodes in this community are weakly interconnected._
- **Why does `vue` connect `Community 35` to `Community 128`, `Community 129`, `Community 4`, `Community 5`, `Community 134`, `Community 135`, `Community 136`, `Community 9`, `Community 137`, `Community 133`, `Community 140`, `Community 17`, `Community 21`, `Community 24`, `Community 152`, `Community 27`, `Community 34`, `Community 166`, `Community 38`, `Community 39`, `Community 169`, `Community 41`, `Community 40`, `Community 48`, `Community 49`, `Community 56`, `Community 57`, `Community 61`, `Community 66`, `Community 70`, `Community 77`, `Community 81`, `Community 89`, `Community 97`, `Community 99`, `Community 104`, `Community 112`, `Community 113`, `Community 114`, `Community 120`, `Community 124`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.008249924173491052 - nodes in this community are weakly interconnected._
- **Why does `Kt()` connect `Community 107` to `Community 8`, `Community 10`, `Community 87`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._