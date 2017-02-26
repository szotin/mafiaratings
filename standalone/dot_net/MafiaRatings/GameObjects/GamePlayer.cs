using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public enum GamePlayerRole
    {
        Civilian = 0,
        Sheriff = 1,
        Mafia = 2,
        Don = 3
    }

    public enum GamePlayerState
    {
        Alive = 0,
        KilledNight = 1,
        KilledDay = 2
    }

    public enum GamePlayerKillReason
    {
        Alive = -1,
        Normal = 0,
        Suicide = 1,
        Warnings = 2,
        KickOut = 3
    }

    public class GamePlayer
    {
        private const int FLAG_IS_MALE = 1;
        private const int FLAG_HAS_IMMUNITY = 2;
        private const int FLAG_MISSES_SPEECH = 4;
        private const int FLAG_SHERIFF_STATUS = 8;

        private int mNumber;
        private int mUserId;
        private int mFlags;
        private string mName;
        private GamePlayerRole mRole;
        private int mWarnings;
        private GamePlayerState mState;
        private int mKillRound;
        private GamePlayerKillReason mKillReason;
        private int mArranged; // what night the player is arranged to be killed. -1 if not arranged.
        private int mDonCheck; // round num when don checked the player; -1 if he didn't
        private int mSheriffCheck; // round num when sheriff checked the player; -1 if he didn't
        private OnStateChange mOnStateChange;

        public delegate void OnStateChange(GamePlayer killedPlayer, GamePlayerKillReason killReason);

        public GamePlayer(int num, OnStateChange onStateChange)
        {
            mNumber = num;
            mName = string.Empty;
            mUserId = -1;
            mFlags = 0;
            mRole = GamePlayerRole.Civilian;
            mWarnings = 0;
            mState = GamePlayerState.Alive;
            mKillRound = -1;
            mArranged = -1;
            mDonCheck = -1;
            mSheriffCheck = -1;
            mKillReason = GamePlayerKillReason.Alive;

            mOnStateChange = onStateChange;
        }

        public GamePlayer(int num, GameReader reader, OnStateChange onStateChange)
        {
            mNumber = num;
            mUserId = reader.NextInt();
            mName = reader.NextString();
            mFlags = reader.NextInt();
            mRole = (GamePlayerRole)reader.NextInt();
            mWarnings = Math.Max(Math.Min(reader.NextInt(), 4), 0);
            mState = (GamePlayerState)reader.NextInt();
            mKillRound = reader.NextInt();
            mKillReason = (GamePlayerKillReason)reader.NextInt();
            mArranged = reader.NextInt();
            mDonCheck = reader.NextInt();
            mSheriffCheck = reader.NextInt();
            if (reader.Version == 0)
            {
                if (reader.NextInt() != 0)
                {
                    mFlags |= FLAG_MISSES_SPEECH;
                }
            }
            else if (reader.Version < 6)
            {
                reader.NextInt(); // there was a counter how many times user anounsed himsel the sheriff. Removed because moderators never used it.
            }

            mOnStateChange = onStateChange;
        }

        public void Write(GameWriter writer)
        {
            writer.Add(mUserId);
            writer.Add(mName);
            writer.Add(mFlags);
            writer.Add((int)mRole);
            writer.Add(mWarnings);
            writer.Add((int)mState);
            writer.Add(mKillRound);
            writer.Add((int)mKillReason);
            writer.Add(mArranged);
            writer.Add(mDonCheck);
            writer.Add(mSheriffCheck);
        }

        public void Kill(bool isNight, GamePlayerKillReason reason, int round)
        {
            if (mState != GamePlayerState.Alive)
            {
                return;
            }

            if (isNight)
            {
                mState = GamePlayerState.KilledNight;
            }
            else
            {
                mState = GamePlayerState.KilledDay;
            }
            mKillRound = round;
            mKillReason = reason;

            if (mOnStateChange != null)
            {
                mOnStateChange(this, reason);
            }
        }

        public void Resurrect()
        {
            if (mState == GamePlayerState.Alive)
            {
                return;
            }

            mState = GamePlayerState.Alive;
            mKillRound = -1;
            GamePlayerKillReason killReason = mKillReason;
            mKillReason = GamePlayerKillReason.Alive;

            if (mOnStateChange != null)
            {
                mOnStateChange(this, killReason);
            }
        }

        public void Warn(bool isNight, int round)
        {
            if (mState != GamePlayerState.Alive)
            {
                return;
            }

            switch (++mWarnings)
            {
                case 3:
                    mFlags |= FLAG_MISSES_SPEECH;
                    break;
                case 4:
                    Kill(isNight, GamePlayerKillReason.Warnings, round);
                    break;
            }
        }

        public void Unwarn()
        {
            if (mWarnings >= 4)
            {
                mWarnings = 3;
                Resurrect();
            }
            else if (mWarnings == 3)
            {
                mWarnings = 2;
                mFlags &= ~FLAG_MISSES_SPEECH;
            }
            else if (mWarnings > 0)
            {
                --mWarnings;
            }
        }

        public void SetUser(Registration reg)
        {
            if (reg == null)
            {
                mName = string.Empty;
                mUserId = -1;
                mFlags = 0;
            }
            else
            {
                mName = reg.Nickname;
                mUserId = reg.UserId;
                mFlags = 0;
                if (reg.User.IsMale)
                {
                    mFlags |= FLAG_IS_MALE;
                }
                if (reg.User.HasImmunity)
                {
                    mFlags |= FLAG_HAS_IMMUNITY;
                }
            }
        }

        public void Reset()
        {
            if (Database.Users.Contains(mUserId))
            {
                Database.Users[mUserId].HasImmunity =
                    mKillRound == 0 &&
                    mKillReason == GamePlayerKillReason.Normal &&
                    mState == GamePlayerState.KilledNight;
            }

            mName = string.Empty;
            mUserId = -1;
            mFlags = 0;
            mRole = GamePlayerRole.Civilian;
            mWarnings = 0;
            mState = GamePlayerState.Alive;
            mKillRound = -1;
            mArranged = -1;
            mDonCheck = -1;
            mSheriffCheck = -1;
            mKillReason = GamePlayerKillReason.Alive;
        }

        public int UserId
        {
            get { return mUserId; }
        }

        public string Name
        {
            get { return mName; }
        }

        public GamePlayerRole Role
        {
            get { return mRole; }
            set { mRole = value; }
        }

        public int Warnings
        {
            get { return mWarnings; }
        }

        public GamePlayerState State
        {
            get { return mState; }
        }

        public int KillRound
        {
            get { return mKillRound; }
        }

        public GamePlayerKillReason KillReason
        {
            get { return mKillReason; }
        }

        public int Arranged
        {
            get { return mArranged; }
            set { mArranged = value; }
        }

        public int DonCheck
        {
            get { return mDonCheck; }
            set { mDonCheck = value; }
        }

        public int SheriffCheck
        {
            get { return mSheriffCheck; }
            set { mSheriffCheck = value; }
        }

        public bool HasImmunity
        {
            get { return (mFlags & FLAG_HAS_IMMUNITY) != 0; }
        }

        public bool MissesSpeech
        {
            get { return (mFlags & FLAG_MISSES_SPEECH) != 0; }
            set
            {
                if (value)
                {
                    mFlags |= FLAG_MISSES_SPEECH;
                }
                else
                {
                    mFlags &= ~FLAG_MISSES_SPEECH;
                }
            }
        }

        public bool IsMale
        {
            get { return (mFlags & FLAG_IS_MALE) != 0; }
        }

        public bool SheriffStatus
        {
            get { return (mFlags & FLAG_SHERIFF_STATUS) != 0; }
        }

        public bool Initialized
        {
            get { return mUserId >= 0; }
        }

        public bool IsDummy
        {
            get { return mUserId == 0; }
        }

        public bool IsAlive
        {
            get { return mState == GamePlayerState.Alive; }
        }

        public bool IsDead
        {
            get { return mState != GamePlayerState.Alive; }
        }

        public int Number
        {
            get { return mNumber; }
        }

        public override string ToString()
        {
            return mName;
        }

        public string SheriffCheckText
        {
            get
            {
                if (mSheriffCheck >= 0)
                {
                    return string.Format(Strings.SheriffCheckText, mSheriffCheck + 1);
                }
                return string.Empty;
            }
        }

        public string DonCheckText
        {
            get
            {
                if (mDonCheck >= 0)
                {
                    return string.Format(Strings.DonCheckText, mDonCheck + 1);
                }
                return string.Empty;
            }
        }

        public string ArrangedText
        {
            get
            {
                if (mArranged >= 0)
                {
                    return string.Format(Strings.ArrangedText, mArranged + 1);
                }
                return string.Empty;
            }
        }

        public string KilledText
        {
            get
            {
                string daytime = string.Empty;
                switch (mState)
                {
                    case GamePlayerState.Alive:
                        return string.Empty;
                    case GamePlayerState.KilledNight:
                        daytime = Strings.Night;
                        break;
                    case GamePlayerState.KilledDay:
                        daytime = Strings.Day;
                        break;
                }

                string reason = string.Empty;
                switch (mKillReason)
                {
                    case GamePlayerKillReason.Suicide:
                        reason = Strings.Suicide;
                        break;
                    case GamePlayerKillReason.Warnings:
                        reason = Strings.Warnings;
                        break;
                    case GamePlayerKillReason.KickOut:
                        reason = Strings.KickedOut;
                        break;
                }
                return string.Format(Strings.KilledText, daytime, mKillRound + 1, reason);
            }
        }

        public string WarningsText
        {
            get
            {
                if (mWarnings <= 0)
                {
                    return string.Empty;
                }
                if (mWarnings == 1)
                {
                    return Strings.WarningText;
                }
                return string.Format(Strings.WarningsText, mWarnings);
            }
        }

        public string RoleText
        {
            get
            {
                switch (mRole)
                {
                    case GamePlayerRole.Sheriff:
                        return Strings.Sheriff;
                    case GamePlayerRole.Don:
                        return Strings.Don;
                    case GamePlayerRole.Mafia:
                        return Strings.Mafia;
                }
                return string.Empty;
            }
        }
    }
}
