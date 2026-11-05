"""Which language the customer writes in: decided in code, so the bot answers in the right language and script."""
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.language import ENGLISH, HINDI, HINGLISH, detect, of_customer  # noqa: E402


class DetectTest(unittest.TestCase):
    def test_devanagari_is_hindi(self):
        self.assertEqual(detect("आप कौन सी सेवाएं देते हैं?"), HINDI)

    def test_hindi_in_english_letters_is_hinglish(self):
        self.assertEqual(detect("Mujhe website banwana hai"), HINGLISH)
        self.assertEqual(detect("Kitne ka padega?"), HINGLISH)
        self.assertEqual(detect("Haan"), HINGLISH)

    def test_plain_english(self):
        self.assertEqual(detect("Do you build mobile apps?"), ENGLISH)

    def test_no_clue(self):
        self.assertIsNone(detect("Ok"))
        self.assertIsNone(detect("👍"))
        self.assertIsNone(detect("500"))


class OfCustomerTest(unittest.TestCase):
    def test_the_latest_message_decides(self):
        self.assertEqual(of_customer("Do you build mobile apps?", [{"from": "customer", "text": "Mujhe website chahiye"}]), ENGLISH)

    def test_a_short_reply_takes_the_customer_s_earlier_language(self):
        history = [{"from": "customer", "text": "आपकी सेवाएं क्या हैं?"}, {"from": "business", "text": "We build websites and apps for you."}]
        self.assertEqual(of_customer("Ok", history), HINDI)

    def test_the_business_lines_are_not_the_customer_s_language(self):
        self.assertEqual(of_customer("Ok", [{"from": "business", "text": "Aapko kya chahiye bhai?"}]), ENGLISH)


if __name__ == "__main__":
    unittest.main()
