using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class GameRules
    {
        private const int FLAG_DEFENSIVE_ROUND = 1;
        private const int FLAG_FREE_ROUND = 2;
        private const int FLAG_DAY1_NO_KILL = 4;
        private const int FLAG_NO_CRASH_4 = 8;
        private const int FLAG_NIGHT_KILL_CAN_NOMINATE = 16;

        private string mName;
        private int mId;
        private int mFlags;
        private int mStFree; // St stands for speech time
        private int mSptFree; // spt stands for speech prompt time: time left to the end of the speech when moderator prompts player
        private int mStReg;
        private int mSptReg;
        private int mStKilled;
        private int mSptKilled;
        private int mStDef;
        private int mSptDef;

        public GameRules()
        {
            mName = "";
            mId = 1;
            mFlags = FLAG_DAY1_NO_KILL | FLAG_NO_CRASH_4 | FLAG_NIGHT_KILL_CAN_NOMINATE;
            mStFree = 0;
            mSptFree = 0;
            mStReg = 60;
            mSptReg = 10;
            mStKilled = 30;
            mSptKilled = 5;
            mStDef = 30;
            mSptDef = 5;
        }

        public GameRules(DataReader reader, int version = Database.VERSION)
        {
            mName = reader.NextString();
            mId = reader.NextInt();
            mFlags = reader.NextInt();
            mStFree = reader.NextInt();
            mStReg = reader.NextInt();
            mStKilled = reader.NextInt();
            mStDef = reader.NextInt();
            mSptFree = reader.NextInt();
            mSptReg = reader.NextInt();
            mSptKilled = reader.NextInt();
            mSptDef = reader.NextInt();
        }

        public void Write(DataWriter writer)
        {
            writer.Add(mName);
            writer.Add(mId);
            writer.Add(mFlags);
            writer.Add(mStFree);
            writer.Add(mStReg);
            writer.Add(mStKilled);
            writer.Add(mStDef);
            writer.Add(mSptFree);
            writer.Add(mSptReg);
            writer.Add(mSptKilled);
            writer.Add(mSptDef);
        }

        public string Name { get { return mName; } }
        public int Id { get { return mId; } }
        public int StFree { get { return mStFree; } }
        public int SptFree { get { return mSptFree; } }
        public int StReg { get { return mStReg; } }
        public int SptReg { get { return mSptReg; } }
        public int StKilled { get { return mStKilled; } }
        public int SptKilled { get { return mSptKilled; } }
        public int StDef { get { return mStDef; } }
        public int SptDef { get { return mSptDef; } }

        public bool DefensiveRound { get { return (mFlags & FLAG_DEFENSIVE_ROUND) != 0; } }
        public bool FreeRound { get { return (mFlags & FLAG_FREE_ROUND) != 0; } }
        public bool Day1NoKill { get { return (mFlags & FLAG_DAY1_NO_KILL) != 0; } }
        public bool NoCrash4 { get { return (mFlags & FLAG_NO_CRASH_4) != 0; } }
        public bool NightKillCanNominate { get { return (mFlags & FLAG_NIGHT_KILL_CAN_NOMINATE) != 0; } }

        public override string ToString()
        {
            if (mName.Length > 0)
            {
                return mName;
            }
            return Strings.DefRules;
        }
    }
}
