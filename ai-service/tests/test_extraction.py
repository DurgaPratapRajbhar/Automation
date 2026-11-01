"""Garbage in must not reach the AI: tests for text extraction and cleaning (no database, no network)."""
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.extraction import UnreadableFile, clean_text, extract_text  # noqa: E402

SHOP = "We sell rice, wheat and spices. Open 9am to 9pm, every day of the week.".encode()


class ExtractionTests(unittest.TestCase):
    def test_plain_text_is_read_and_tidied(self):
        text = extract_text("info.txt", b"\xef\xbb\xbf" + SHOP + b"\r\n\r\n\r\n\r\nMore   spaced   words here for the shop.")
        self.assertTrue(text.startswith("We sell rice"))
        self.assertNotIn("\r", text)
        self.assertNotIn("   ", text)

    def test_binary_renamed_to_txt_is_refused(self):
        with self.assertRaises(UnreadableFile):
            extract_text("prices.txt", bytes(range(256)) * 20)

    def test_non_utf8_bytes_are_not_guessed_as_text(self):
        with self.assertRaises(UnreadableFile):
            extract_text("old.txt", "Dukaan ka daam caf\xe9 aur naan".encode("latin-1") + b"\xff\xfe" * 40)

    def test_old_word_and_unknown_types_are_refused(self):
        for name in ("offer.doc", "run.exe", "noext"):
            with self.assertRaises(UnreadableFile):
                extract_text(name, SHOP)

    def test_corrupt_pdf_and_docx_are_refused(self):
        for name in ("a.pdf", "b.docx"):
            with self.assertRaises(UnreadableFile):
                extract_text(name, b"this is not really a document file at all, just words")

    def test_empty_or_symbol_only_text_is_refused(self):
        for junk in ("", "   \n\n  ", "!!! ### $$$ %%% ^^^ &&& *** ((( ))) ___ +++ === --- ~~~ ,,, ...", "12345 67890 " * 10):
            with self.assertRaises(UnreadableFile):
                clean_text(junk)

    def test_control_characters_are_removed(self):
        self.assertEqual(clean_text("Hello\x00 shop\x07 owner, welcome to our store today."), "Hello shop owner, welcome to our store today.")


if __name__ == "__main__":
    unittest.main()
