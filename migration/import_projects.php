#!/usr/bin/env php
<?php

$root = dirname(dirname(__FILE__));
require_once $root.'/scripts/__init_script__.php';
require_once 'storage.php';
require_once 'adapt.php';
require_once 'phab.php';

/* projects */
create_project("BF Blender", "None", true, $bf_developers, "Blender Foundation official release.");
create_project("Addons", "None", true, $addon_developers, "Addon scripts and plugins for Blender.");
create_project("Translations", "None", true, $translation_developers, "Localization of Blender in different languages.");

/* modules */
//create_project("None", "None", true, array(), ""); // None
create_project("Animation", "None", true, array(), "This project includes the graph editor, dopespheet editor, NLA editor, keyframes, drivers and more."); // Animation system
create_project("Audio", "None", true, array(), "Sound import, export, editing and playback."); // Audio
create_project("Compositing", "None", true, array(), "Node based compositing."); // Compositor
create_project("Dependency Graph", "None", true, array(), "System to evaluate and update objects for editing and animation."); // Depsgraph
//create_project("FFMPEG", "None", true, array(), ""); // FFMPEG
create_project("Freestyle", "None", true, array(), "Non-photorealistic rendering with lines."); // Freestyle
create_project("Images & Movies", "None", true, array(), "Import and export of images and movies"); // Image &amp; Movie I/O
create_project("Import/Export", "None", true, array(), "Import and export of data from and to external file formats."); // Import/Export
create_project("User Interface", "None", true, array(), "User interface of Blender."); // Interface
//create_project("International", "None", true, array(), ""); // International
create_project("Masking", "None", true, array(), "Mask editing and use for compositing."); // Masking
create_project("Mesh Modeling", "None", true, array(), "Mesh modeling tools."); // Mesh Modeling
create_project("Modifiers", "None", true, array(), "Modifier stack for meshes, curves, metaballs."); // Modifiers
create_project("Motion Tracking", "None", true, array(), "Motion tracking for VFX."); // Motion tracking
create_project("Nodes", "None", true, array(), "Node editor."); // Node Editor
create_project("FreeBSD", "None", true, array(), "FreeBSD platform support."); // OS related: FreeBSD
create_project("Linux", "None", true, array(), "Linux platform support."); // OS related: Linux
create_project("Mac OS X", "None", true, array(), "Mac OS X platform support."); // OS related: OSX
create_project("Windows", "None", true, array(), "Windows platform support."); // OS related: Windows
create_project("OpenGL / Gfx", "None", true, array(), "OpenGL and graphics driver or card related topics."); // Opengl / Gfx
create_project("Physics", "None", true, array(), "Physics simulation systems including rigid bodies, cloth, softbodies, smoke fluids and particles."); // Physics
create_project("Python", "None", true, array(), "Python API for scripting."); // Python
create_project("Rendering", "None", true, array(), "Blender internal renderer and general rendering pipeline."); // Rendering
create_project("Cycles", "None", true, array(), "Raytracing based production renderer built into Blender."); // Rendering: Cycles
//create_project("Scripts", "None", true, array(), ""); // Scripts
create_project("Sculpting", "None", true, array(), "Mesh sculpting."); // Sculpting
create_project("Video Sequencer", "None", true, array(), "Video editor built into Blender."); // Sequencer
create_project("Text Editor", "None", true, array(), "Text editor built into Blender."); // Text editor
//create_project("Tools", "None", true, array(), ""); // Tools
create_project("Game Engine", "None", true, array(), "Blender game engine.");

