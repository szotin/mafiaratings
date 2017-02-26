using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class Language
    {
        public const int NO = 0;
        public const int ENGLISH = 1;
        public const int RUSSIAN = 2;
        public const int ALL = 3;

        private int mLang;

        public Language(int lang)
        {
            mLang = lang;
        }

        public int Lang
        {
            get { return mLang; }
            set { mLang = value; }
        }

        public override string ToString()
        {
            return getLangStr(mLang);
        }

        public static string getLangStr(int lang)
        {
	        switch (lang)
	        {
                case NO:
                    return "";
		        case ENGLISH:
			        return Strings.English;
		        case RUSSIAN:
                    return Strings.Russian;
            }
	        return Strings.Unknown;
        }

        public static string getLangCode(int lang)
        {
	        switch (lang)
	        {
		        case RUSSIAN:
			        return "ru";
	        }
	        return "en";
        }

        public static int GetLangByCode(string code)
        {
            if (code == "ru")
            {
                return RUSSIAN;
            }
	        return ENGLISH;
        }

        public static int GetNextLang(int lang, int langs = ALL)
        {
	        if (lang != NO)
	        {
		        langs &= (~(lang - 1)) << 1;
	        }
	        langs -= ((langs - 1) & langs);
	        return langs;
        }

        public static string GetLangsStr(int langs, string separator)
        {
	        StringBuilder str = new StringBuilder();
	        string sep = string.Empty;
	        int lang = NO;
	        while ((lang = GetNextLang(lang, langs)) != NO)
	        {
                str.Append(sep);
                str.Append(getLangStr(lang));
		        sep = separator;
	        }
	        return str.ToString();
        }

        public static string GetLangsCodes(int langs, string separator)
        {
	        StringBuilder str = new StringBuilder();
	        string sep = string.Empty;
	        int lang = NO;
	        while ((lang = GetNextLang(lang, langs)) != NO)
	        {
                str.Append(sep);
                str.Append(getLangCode(lang));
		        sep = separator;
	        }
	        return str.ToString();
        }

        public static int GetLangsCount(int langs)
        {
	        int count = 0;
	        while (langs != 0)
	        {
		        ++count;
		        langs &= (langs - 1);
	        }
	        return count;
        }

        public static bool IsValidLang(int lang, int langs = ALL)
        {
	        if (((lang - 1) & lang) != 0)
	        {
		        return false;
	        }
	        return ((lang & langs) != 0);
        }
    }
}
