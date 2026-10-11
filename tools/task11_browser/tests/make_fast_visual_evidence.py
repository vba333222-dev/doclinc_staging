from pathlib import Path
import shutil
import sys
from PIL import Image, ImageChops

out = Path(sys.argv[1])
chat_reference = Path(sys.argv[2])
chat_actual = out / 'chat-actual-468.png'

def copy_and_overlay(reference, actual, reference_name, actual_name, overlay_name, difference_name):
    if not reference.exists() or not actual.exists():
        raise SystemExit(f'missing_visual_input:{reference}:{actual}')
    source_ref = Image.open(reference).convert('RGBA')
    # The supplied reference PNG is the approved 390x1078 production surface.
    # Place it at native CSS geometry on the required review canvas; do not
    # scale it, since the actual production capture preserves the same shell
    # width inside the 468px evidence canvas.
    ref = Image.new('RGBA', (468, 1600), source_ref.getpixel((0, 0)))
    fitted = source_ref.copy()
    ref.alpha_composite(fitted, ((468 - fitted.width) // 2, (1600 - fitted.height) // 2))
    act = Image.open(actual).convert('RGBA')
    if act.size != (468, 1600) or ref.size != act.size:
        raise SystemExit(f'chat_canvas_mismatch:reference={ref.size}:actual={act.size}')
    ref.save(out / reference_name)
    if Path(actual).resolve() != (out / actual_name).resolve():
        shutil.copyfile(actual, out / actual_name)
    Image.blend(ref, act, 0.5).save(out / overlay_name)
    ImageChops.difference(ref, act).save(out / difference_name)

copy_and_overlay(chat_reference, chat_actual, 'chat-reference-468.png', 'chat-actual-468.png', 'chat-overlay-468.png', 'chat-difference-468.png')
