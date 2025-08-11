# Floor Plan Editor Documentation

## Overview

The Floor Plan Editor is a drag-and-drop interface that allows administrators to visually design room layouts for seat reservations. It provides a flexible canvas where blocks, rows, and seats can be positioned and configured according to the room's physical layout.

## Features

### 🎨 Visual Design
- **Drag & Drop**: Move blocks freely within the room canvas
- **Grid Snapping**: Align elements precisely with optional grid snapping
- **Rotation**: Rotate blocks in 90° increments for flexible seating arrangements
- **Stage Elements**: Add and position stage elements for venues
- **Z-Index Control**: Layer elements properly with bring-to-front/send-to-back

### 🏗️ Block Management
- **Create Blocks**: Add new seating blocks with custom row and seat configurations
- **Edit Properties**: Modify block names, positions, and seating arrangements
- **Row Configuration**: Set up rows with customizable seat counts
- **Seat Layout**: Automatic seat labeling (A, B, C, etc.)

### 🎛️ Editor Controls
- **Edit/Preview Modes**: Switch between editing and viewing modes
- **Canvas Sizing**: Adjust canvas dimensions with presets or custom sizes
- **Grid Toggle**: Show/hide alignment grid
- **Real-time Updates**: See changes instantly as you edit

## How to Access

1. **Login** as an administrator
2. **Navigate** to Admin Panel → Rooms
3. **Click** the "Floor Plan Editor" button next to any room
4. The editor will open in a new tab with the room's current layout

## Using the Editor

### Creating Blocks

1. Click **"Add Block"** in the toolbar
2. A new block will appear on the canvas
3. Use the **Properties Panel** to configure:
   - Block name
   - Number of rows
   - Seats per row
   - Position and rotation

### Moving Elements

1. **Click and drag** any block to move it
2. Elements will **snap to grid** if grid mode is enabled
3. Use the **Properties Panel** for precise positioning

### Rotating Blocks

1. **Select a block** by clicking on it
2. Click the **"Rotate"** button in the toolbar
3. Or use the **rotation handle** above selected blocks
4. Blocks rotate in 90° increments

### Adding a Stage

1. Click **"Add Stage"** in the toolbar
2. Position and resize the stage as needed
3. Useful for theaters, concert venues, etc.

### Configuring Rows and Seats

1. **Select a block** to open the Properties Panel
2. **Add/Remove Rows** using the row controls
3. **Adjust seat counts** for each row individually
4. **Preview seats** in real-time

### Saving Changes

1. Click **"Save Layout"** to persist changes
2. For new blocks: Click **"Create New Blocks"** first
3. Then save the overall layout

## Technical Details

### Data Model
- **Rooms** contain multiple **Blocks**
- **Blocks** contain multiple **Rows**
- **Rows** contain multiple **Seats**
- Each element has position (x, y), rotation, and z-index properties

### Database Fields
- `rooms.layout_config`: JSON containing canvas settings and stage info
- `blocks.position_x/position_y`: Block coordinates
- `blocks.rotation`: Rotation angle (0, 90, 180, 270)
- `blocks.z_index`: Layer order

### Browser Support
- Modern browsers with ES6+ support
- Touch-enabled devices supported
- Recommended: Chrome, Firefox, Safari, Edge

## Keyboard Shortcuts

- **Delete/Backspace**: Remove selected element
- **Ctrl+R**: Rotate selected element
- **Escape**: Clear selection

## Tips & Best Practices

1. **Start with Stage**: Add the stage first to orient other elements
2. **Use Grid**: Enable grid for professional, aligned layouts
3. **Test Preview**: Switch to preview mode to see the user experience
4. **Save Frequently**: Save your work regularly to avoid data loss
5. **Block Naming**: Use clear, descriptive names for blocks (e.g., "Orchestra Left", "Balcony Center")

## Troubleshooting

### Changes Not Saving
- Ensure you have admin privileges
- Check browser console for errors
- Try refreshing the page and re-editing

### Elements Not Moving
- Make sure you're in Edit mode (not Preview)
- Click on the element to select it first
- Check that you're dragging from the element itself

### Missing Seats
- Verify the seat count in the Properties Panel
- Check that rows are properly configured
- Ensure the block has been saved

## URL Structure

The floor plan editor is accessible at:
```
/admin/rooms/{room_id}/floor-plan/editor
```

Where `{room_id}` is the ID of the room you want to edit.
