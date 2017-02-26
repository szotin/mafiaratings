using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Collections;

namespace MafiaRatings.GameObjects
{
    public class GameRulesList : IEnumerable
    {
        private GameRules[] mRules;

        public GameRulesList()
        {
            mRules = new GameRules[1];
            mRules[0] = new GameRules();
        }

        public GameRulesList(DataReader reader, int version = Database.VERSION)
        {
            if (version > 1)
            {
                mRules = new GameRules[reader.NextInt()];
                for (int i = 0; i < mRules.Length; ++i)
                {
                    mRules[i] = new GameRules(reader, version);
                }
            }
            else
            {
                mRules = new GameRules[1];
                mRules[0] = new GameRules();
            }
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mRules.Length);
            for (int i = 0; i < mRules.Length; ++i)
            {
                mRules[i].Write(writer);
            }
        }

        public int Count
        {
            get { return mRules.Length; }
        }

        public GameRules this[int id]
        {
            get
            {
                for (int i = 0; i < mRules.Length; ++i)
                {
                    GameRules rules = mRules[i];
                    if (rules.Id == id)
                    {
                        return rules;
                    }
                }
                return null;
            }
        }

        public bool Contains(int id)
        {
            return this[id] != null;
        }

        public int RuleIndex(int id)
        {
            for (int i = 0; i < mRules.Length; ++i)
            {
                GameRules rules = mRules[i];
                if (rules.Id == id)
                {
                    return i;
                }
            }
            return 0;
        }

        public IEnumerator GetEnumerator()
        {
            return mRules.GetEnumerator();
        }
    }
}
